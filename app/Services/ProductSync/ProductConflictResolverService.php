<?php

namespace App\Services\ProductSync;

use App\Models\ProductSyncConflict;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ProductConflictResolverService
{
    protected ShopifyProductService $shopifyService;
    protected ProductSyncLogService $logService;

    public function __construct(
        ShopifyProductService $shopifyService,
        ProductSyncLogService $logService
    ) {
        $this->shopifyService = $shopifyService;
        $this->logService = $logService;
    }

    /**
     * Resolve a product conflict
     *
     * @param ProductSyncConflict $conflict
     * @param string $resolutionSource
     * @param mixed $customValue
     * @param string|null $resolvedBy
     * @return array
     */
    public function resolveConflict(
        ProductSyncConflict $conflict,
        string $resolutionSource,
        $customValue = null,
        ?string $resolvedBy = null
    ): array {
        try {
            // Validate resolution source
            if (!in_array($resolutionSource, ['ps_restful', 'shopify', 'custom', 'ignored'])) {
                return [
                    'success' => false,
                    'error' => 'Invalid resolution source',
                ];
            }

            // Validate custom value if needed
            if ($resolutionSource === 'custom' && $customValue === null) {
                return [
                    'success' => false,
                    'error' => 'Custom value is required for custom resolution',
                ];
            }

            // Handle ignore
            if ($resolutionSource === 'ignored') {
                return $this->ignoreConflict($conflict, $resolvedBy);
            }

            // Determine the value to apply
            $valueToApply = $this->determineValueToApply(
                $conflict,
                $resolutionSource,
                $customValue
            );

            // Update Shopify if needed
            $shouldUpdateShopify = $this->shouldUpdateShopify($conflict, $resolutionSource);

            if ($shouldUpdateShopify) {
                $updateSuccess = $this->updateShopifyProduct($conflict, $valueToApply);

                if (!$updateSuccess) {
                    $this->logService->logShopifyUpdateFailed(
                        $conflict->product_id,
                        "Failed to update field: {$conflict->field_name}",
                        $conflict->shop_id
                    );

                    return [
                        'success' => false,
                        'error' => 'Failed to update Shopify product',
                    ];
                }
            }

            // Mark conflict as resolved
            $conflict->markAsResolved($resolutionSource, $valueToApply, $resolvedBy);

            // Update local product record
            $this->updateLocalProduct($conflict, $valueToApply);

            // Log resolution
            $this->logService->logConflictResolved(
                $conflict->id,
                $conflict->product_id,
                $conflict->field_name,
                $resolutionSource,
                $conflict->shop_id
            );

            return [
                'success' => true,
                'message' => 'Conflict resolved successfully',
                'updated_shopify' => $shouldUpdateShopify,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to resolve conflict', [
                'conflict_id' => $conflict->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Ignore a conflict
     *
     * @param ProductSyncConflict $conflict
     * @param string|null $resolvedBy
     * @return array
     */
    protected function ignoreConflict(ProductSyncConflict $conflict, ?string $resolvedBy = null): array
    {
        $conflict->markAsIgnored($resolvedBy);

        $this->logService->logConflictIgnored(
            $conflict->id,
            $conflict->product_id,
            $conflict->field_name,
            $conflict->shop_id
        );

        return [
            'success' => true,
            'message' => 'Conflict ignored',
            'updated_shopify' => false,
        ];
    }

    /**
     * Determine the value to apply based on resolution source
     *
     * @param ProductSyncConflict $conflict
     * @param string $resolutionSource
     * @param mixed $customValue
     * @return mixed
     */
    protected function determineValueToApply(
        ProductSyncConflict $conflict,
        string $resolutionSource,
        $customValue = null
    ) {
        switch ($resolutionSource) {
            case 'ps_restful':
                return $this->parseValue($conflict->ps_value, $conflict->field_name);

            case 'shopify':
                return $this->parseValue($conflict->shopify_value, $conflict->field_name);

            case 'custom':
                return $customValue;

            default:
                return null;
        }
    }

    /**
     * Parse stored value based on field type
     *
     * @param string $value
     * @param string $fieldName
     * @return mixed
     */
    protected function parseValue(string $value, string $fieldName)
    {
        if ($fieldName === 'tags') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }

        if (in_array($fieldName, ['price', 'compare_at_price'])) {
            return (float) $value;
        }

        if ($fieldName === 'inventory_quantity') {
            return (int) $value;
        }

        return $value;
    }

    /**
     * Determine if Shopify should be updated
     *
     * @param ProductSyncConflict $conflict
     * @param string $resolutionSource
     * @return bool
     */
    protected function shouldUpdateShopify(ProductSyncConflict $conflict, string $resolutionSource): bool
    {
        // Don't update Shopify if using Shopify's value
        if ($resolutionSource === 'shopify') {
            return false;
        }

        // Update Shopify for PS RESTful or custom values
        return in_array($resolutionSource, ['ps_restful', 'custom']);
    }

    /**
     * Update Shopify product with resolved value
     *
     * @param ProductSyncConflict $conflict
     * @param mixed $value
     * @return bool
     */
    protected function updateShopifyProduct(ProductSyncConflict $conflict, $value): bool
    {
        $product = $conflict->product;

        if (!$product || !$product->shopify_product_id) {
            Log::error('Cannot update Shopify: product or Shopify ID missing', [
                'conflict_id' => $conflict->id,
            ]);
            return false;
        }

        // Load the shop
        $shop = User::find($product->shop_id);
        
        if (!$shop) {
            Log::error('Cannot update Shopify: shop not found', [
                'conflict_id' => $conflict->id,
                'shop_id' => $product->shop_id,
            ]);
            return false;
        }

        $updates = [
            $conflict->field_name => $value,
        ];

        return $this->shopifyService->updateProduct(
            $shop,
            $product->shopify_product_id,
            $updates
        );
    }

    /**
     * Update local product record with resolved value
     *
     * @param ProductSyncConflict $conflict
     * @param mixed $value
     * @return void
     */
    protected function updateLocalProduct(ProductSyncConflict $conflict, $value): void
    {
        $product = $conflict->product;

        if (!$product) {
            return;
        }

        $product->update([
            $conflict->field_name => $value,
        ]);
    }

    /**
     * Resolve multiple conflicts at once
     *
     * @param array $resolutions
     * @param string|null $resolvedBy
     * @return array
     */
    public function resolveMultipleConflicts(array $resolutions, ?string $resolvedBy = null): array
    {
        $results = [
            'total' => count($resolutions),
            'successful' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($resolutions as $resolution) {
            $conflict = ProductSyncConflict::find($resolution['conflict_id']);

            if (!$conflict) {
                $results['failed']++;
                $results['errors'][] = "Conflict {$resolution['conflict_id']} not found";
                continue;
            }

            $result = $this->resolveConflict(
                $conflict,
                $resolution['resolution_source'],
                $resolution['custom_value'] ?? null,
                $resolvedBy
            );

            if ($result['success']) {
                $results['successful']++;
            } else {
                $results['failed']++;
                $results['errors'][] = $result['error'] ?? 'Unknown error';
            }
        }

        return $results;
    }
}
