<?php

namespace App\Services\ProductSync;

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductSyncService
{
    protected PsRestfulProductService $psRestfulService;
    protected ShopifyProductService $shopifyService;
    protected ProductConflictService $conflictService;
    protected ProductSyncLogService $logService;

    public function __construct(
        PsRestfulProductService $psRestfulService,
        ShopifyProductService $shopifyService,
        ProductConflictService $conflictService,
        ProductSyncLogService $logService
    ) {
        $this->psRestfulService = $psRestfulService;
        $this->shopifyService = $shopifyService;
        $this->conflictService = $conflictService;
        $this->logService = $logService;
    }

    /**
     * Sync products from Akeneo PIM to Shopify
     *
     * @param int|null $shopId
     * @param array $options
     * @return array
     */
    public function syncProducts(?int $shopId = null, array $options = []): array
    {
        $syncStartedAt = now();
        
        // Log sync started
        $this->logService->logSyncStarted($shopId);

        $summary = [
            'total_ps_products' => 0,
            'created_in_shopify' => 0,
            'updated_local_products' => 0,
            'conflicts_detected' => 0,
            'conflicts_updated' => 0,
            'failed_products' => 0,
            'sync_started_at' => $syncStartedAt,
            'sync_completed_at' => null,
        ];

        try {
            // Load the shop by ID
            if (!$shopId) {
                return [
                    'success' => false,
                    'error' => 'Shop ID is required',
                    'summary' => $summary,
                ];
            }

            $shop = User::find($shopId);
            
            if (!$shop) {
                Log::error('Shop not found', ['shop_id' => $shopId]);
                return [
                    'success' => false,
                    'error' => 'Shop not found',
                    'summary' => $summary,
                ];
            }

            // Fetch products from Akeneo
            $psResult = $this->psRestfulService->fetchProducts($options);

            if (!$psResult['success']) {
                $this->logService->logPsRestfulFetchFailed(
                    $psResult['error'] ?? 'Unknown error',
                    $shopId
                );

                return [
                    'success' => false,
                    'error' => $psResult['error'] ?? 'Failed to fetch Akeneo products',
                    'summary' => $summary,
                ];
            }

            $psProducts = $psResult['products'];
            $summary['total_ps_products'] = count($psProducts);

            Log::info('Starting product sync', [
                'shop_id' => $shopId,
                'total_products' => $summary['total_ps_products'],
            ]);

            // Process each product
            foreach ($psProducts as $psProductData) {
                try {
                    $result = $this->syncSingleProduct($psProductData, $shop);

                    if ($result['created']) {
                        $summary['created_in_shopify']++;
                    }

                    if ($result['updated']) {
                        $summary['updated_local_products']++;
                    }

                    $summary['conflicts_detected'] += $result['conflicts_detected'];
                    $summary['conflicts_updated'] += $result['conflicts_updated'];

                } catch (\Exception $e) {
                    $summary['failed_products']++;
                    
                    Log::error('Failed to sync product', [
                        'ps_product_id' => $psProductData['ps_product_id'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $summary['sync_completed_at'] = now();

            // Log sync completed
            $this->logService->logSyncCompleted($summary, $shopId);

            Log::info('Product sync completed', $summary);

            return [
                'success' => true,
                'summary' => $summary,
            ];

        } catch (\Exception $e) {
            Log::error('Product sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->logService->logSyncFailed($e->getMessage(), $shopId);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'summary' => $summary,
            ];
        }
    }

    /**
     * Sync a single product
     *
     * @param array $psProductData
     * @param User $shop
     * @return array
     */
    protected function syncSingleProduct(array $psProductData, User $shop): array
    {
        $result = [
            'created' => false,
            'updated' => false,
            'conflicts_detected' => 0,
            'conflicts_updated' => 0,
        ];

        DB::beginTransaction();

        try {
            // Find or create local product record
            $product = $this->findOrCreateLocalProduct($psProductData, $shop->id);

            // Check if product exists in Shopify
            $shopifyProduct = null;
            if ($product->shopify_product_id) {
                $shopifyProduct = $this->shopifyService->fetchProductById($shop, $product->shopify_product_id);
            } elseif ($product->sku) {
                $shopifyProduct = $this->shopifyService->fetchProductBySku($shop, $product->sku);
            }

            // Create product in Shopify if it doesn't exist
            if (!$shopifyProduct) {
                $shopifyProduct = $this->shopifyService->createProduct($shop, $psProductData);

                if ($shopifyProduct) {
                    $product->update([
                        'shopify_product_id' => $shopifyProduct['shopify_product_id'],
                        'shopify_variant_id' => $shopifyProduct['shopify_variant_id'],
                    ]);

                    $this->logService->logProductCreated(
                        $product->id,
                        $psProductData,
                        $shop->id
                    );

                    $result['created'] = true;
                } else {
                    throw new \Exception('Failed to create product in Shopify');
                }
            }

            // Compare and detect conflicts
            if ($shopifyProduct) {
                $conflicts = $this->conflictService->detectConflicts(
                    $product,
                    $psProductData,
                    $shopifyProduct
                );

                foreach ($conflicts as $conflict) {
                    if ($conflict->wasRecentlyCreated) {
                        $result['conflicts_detected']++;
                    } else {
                        $result['conflicts_updated']++;
                    }
                }
            }

            // Update local product record
            $product->update([
                'title' => $psProductData['title'],
                'sku' => $psProductData['sku'],
                'description' => $psProductData['description'],
                'vendor' => $psProductData['vendor'],
                'product_type' => $psProductData['product_type'],
                'price' => $psProductData['price'],
                'compare_at_price' => $psProductData['compare_at_price'],
                'inventory_quantity' => $psProductData['inventory_quantity'],
                'status' => $psProductData['status'],
                'tags' => $psProductData['tags'],
                'image_urls' => $psProductData['image_urls'],
                'raw_ps_payload' => $psProductData['raw_payload'] ?? null,
                'raw_shopify_payload' => $shopifyProduct['raw_payload'] ?? null,
                'last_synced_at' => now(),
            ]);

            $this->logService->logProductUpdated(
                $product->id,
                $psProductData,
                $shop->id
            );

            $result['updated'] = true;

            DB::commit();

            return $result;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Find or create local product record
     *
     * @param array $psProductData
     * @param int|null $shopId
     * @return Product
     */
    protected function findOrCreateLocalProduct(array $psProductData, ?int $shopId = null): Product
    {
        // Try to find by PS product ID
        if (!empty($psProductData['ps_product_id'])) {
            $product = Product::where('ps_product_id', $psProductData['ps_product_id'])
                ->when($shopId, fn($q) => $q->where('shop_id', $shopId))
                ->first();

            if ($product) {
                return $product;
            }
        }

        // Try to find by SKU
        if (!empty($psProductData['sku'])) {
            $product = Product::where('sku', $psProductData['sku'])
                ->when($shopId, fn($q) => $q->where('shop_id', $shopId))
                ->first();

            if ($product) {
                return $product;
            }
        }

        // Create new product record
        return Product::create([
            'shop_id' => $shopId,
            'ps_product_id' => $psProductData['ps_product_id'],
            'title' => $psProductData['title'],
            'sku' => $psProductData['sku'],
            'description' => $psProductData['description'],
            'vendor' => $psProductData['vendor'],
            'product_type' => $psProductData['product_type'],
            'price' => $psProductData['price'],
            'compare_at_price' => $psProductData['compare_at_price'],
            'inventory_quantity' => $psProductData['inventory_quantity'],
            'status' => $psProductData['status'],
            'tags' => $psProductData['tags'],
            'image_urls' => $psProductData['image_urls'],
            'raw_ps_payload' => $psProductData['raw_payload'] ?? null,
        ]);
    }

    /**
     * Get sync statistics
     *
     * @param int|null $shopId
     * @return array
     */
    public function getSyncStats(?int $shopId = null): array
    {
        $productsQuery = Product::query();
        $conflictsStats = $this->conflictService->getConflictStats($shopId);

        if ($shopId) {
            $productsQuery->where('shop_id', $shopId);
        }

        $totalProducts = $productsQuery->count();
        $productsWithShopifyId = (clone $productsQuery)->whereNotNull('shopify_product_id')->count();
        $lastSyncedProduct = (clone $productsQuery)->latest('last_synced_at')->first();

        return [
            'total_products' => $totalProducts,
            'shopify_products' => $productsWithShopifyId,
            'pending_conflicts' => $conflictsStats['pending'],
            'resolved_conflicts' => $conflictsStats['resolved'],
            'ignored_conflicts' => $conflictsStats['ignored'],
            'failed_syncs' => $conflictsStats['failed'],
            'last_sync_at' => $lastSyncedProduct?->last_synced_at,
        ];
    }
}
