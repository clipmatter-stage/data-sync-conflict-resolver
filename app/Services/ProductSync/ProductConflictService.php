<?php

namespace App\Services\ProductSync;

use App\Models\Product;
use App\Models\ProductSyncConflict;
use Illuminate\Support\Facades\Log;

class ProductConflictService
{
    protected ProductSyncLogService $logService;

    // Fields to compare between systems
    protected array $comparableFields = [
        'title',
        'sku',
        'description',
        'vendor',
        'product_type',
        'price',
        'compare_at_price',
        'inventory_quantity',
        'status',
        'tags',
    ];

    public function __construct(ProductSyncLogService $logService)
    {
        $this->logService = $logService;
    }

    /**
     * Detect conflicts between PS RESTful and Shopify product data
     *
     * @param Product $product
     * @param array $psData
     * @param array $shopifyData
     * @return array
     */
    public function detectConflicts(Product $product, array $psData, array $shopifyData): array
    {
        $conflicts = [];
        $shopId = $product->shop_id;

        foreach ($this->comparableFields as $field) {
            $psValue = $psData[$field] ?? null;
            $shopifyValue = $shopifyData[$field] ?? null;

            // Normalize and compare values
            if (!$this->valuesMatch($psValue, $shopifyValue, $field)) {
                $conflict = $this->createOrUpdateConflict(
                    $product,
                    $field,
                    $psValue,
                    $shopifyValue,
                    $shopId
                );

                if ($conflict) {
                    $conflicts[] = $conflict;
                }
            }
        }

        return $conflicts;
    }

    /**
     * Create or update a conflict record
     *
     * @param Product $product
     * @param string $fieldName
     * @param mixed $psValue
     * @param mixed $shopifyValue
     * @param int|null $shopId
     * @return ProductSyncConflict|null
     */
    protected function createOrUpdateConflict(
        Product $product,
        string $fieldName,
        $psValue,
        $shopifyValue,
        ?int $shopId = null
    ): ?ProductSyncConflict {
        try {
            // Convert values to strings for storage
            $psValueString = $this->valueToString($psValue);
            $shopifyValueString = $this->valueToString($shopifyValue);

            // Check if a pending conflict already exists for this product and field
            $existingConflict = ProductSyncConflict::where('product_id', $product->id)
                ->where('field_name', $fieldName)
                ->where('status', 'pending')
                ->first();

            if ($existingConflict) {
                // Update existing conflict with new values
                $existingConflict->update([
                    'ps_value' => $psValueString,
                    'shopify_value' => $shopifyValueString,
                    'detected_at' => now(),
                ]);

                $this->logService->logConflictUpdated(
                    $existingConflict->id,
                    $product->id,
                    $fieldName,
                    $shopId
                );

                return $existingConflict;
            }

            // Check if a resolved conflict exists - reopen it if values changed again
            $resolvedConflict = ProductSyncConflict::where('product_id', $product->id)
                ->where('field_name', $fieldName)
                ->where('status', 'resolved')
                ->latest()
                ->first();

            if ($resolvedConflict) {
                // Reopen the conflict with new values
                $resolvedConflict->reopen($psValueString, $shopifyValueString);

                $this->logService->logConflictDetected(
                    $resolvedConflict->id,
                    $product->id,
                    $fieldName,
                    $shopId
                );

                return $resolvedConflict;
            }

            // Create new conflict
            $conflict = ProductSyncConflict::create([
                'shop_id' => $shopId,
                'product_id' => $product->id,
                'ps_product_id' => $product->ps_product_id,
                'shopify_product_id' => $product->shopify_product_id,
                'field_name' => $fieldName,
                'ps_value' => $psValueString,
                'shopify_value' => $shopifyValueString,
                'status' => 'pending',
                'detected_at' => now(),
            ]);

            $this->logService->logConflictDetected(
                $conflict->id,
                $product->id,
                $fieldName,
                $shopId
            );

            return $conflict;

        } catch (\Exception $e) {
            Log::error('Failed to create or update conflict', [
                'product_id' => $product->id,
                'field' => $fieldName,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Check if two values match (accounting for normalization)
     *
     * @param mixed $value1
     * @param mixed $value2
     * @param string $field
     * @return bool
     */
    protected function valuesMatch($value1, $value2, string $field): bool
    {
        // Handle null values
        if ($value1 === null && $value2 === null) {
            return true;
        }

        if ($value1 === null || $value2 === null) {
            return false;
        }

        // Special handling for different field types
        switch ($field) {
            case 'price':
            case 'compare_at_price':
                return $this->pricesMatch($value1, $value2);

            case 'tags':
                return $this->tagsMatch($value1, $value2);

            case 'title':
            case 'description':
            case 'vendor':
            case 'product_type':
                return $this->stringsMatch($value1, $value2);

            case 'inventory_quantity':
                return (int) $value1 === (int) $value2;

            default:
                return $value1 === $value2;
        }
    }

    /**
     * Check if prices match (with tolerance for floating point)
     */
    protected function pricesMatch($price1, $price2): bool
    {
        $p1 = (float) $price1;
        $p2 = (float) $price2;

        return abs($p1 - $p2) < 0.01;
    }

    /**
     * Check if tags match (order-independent)
     */
    protected function tagsMatch($tags1, $tags2): bool
    {
        $t1 = is_array($tags1) ? $tags1 : [];
        $t2 = is_array($tags2) ? $tags2 : [];

        sort($t1);
        sort($t2);

        return $t1 === $t2;
    }

    /**
     * Check if strings match (normalized)
     */
    protected function stringsMatch($str1, $str2): bool
    {
        $s1 = trim((string) $str1);
        $s2 = trim((string) $str2);

        return $s1 === $s2;
    }

    /**
     * Convert value to string for storage
     */
    protected function valueToString($value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        return (string) $value;
    }

    /**
     * Get all pending conflicts for a shop
     */
    public function getPendingConflicts(?int $shopId = null)
    {
        $query = ProductSyncConflict::query()
            ->with('product')
            ->where('status', 'pending')
            ->latest('detected_at');

        if ($shopId) {
            $query->where('shop_id', $shopId);
        }

        return $query->get();
    }

    /**
     * Get conflict statistics
     */
    public function getConflictStats(?int $shopId = null): array
    {
        $query = ProductSyncConflict::query();

        if ($shopId) {
            $query->where('shop_id', $shopId);
        }

        return [
            'total' => $query->count(),
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'resolved' => (clone $query)->where('status', 'resolved')->count(),
            'ignored' => (clone $query)->where('status', 'ignored')->count(),
            'failed' => (clone $query)->where('status', 'failed')->count(),
        ];
    }
}
