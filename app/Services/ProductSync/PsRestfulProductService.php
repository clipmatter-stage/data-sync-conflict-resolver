<?php

namespace App\Services\ProductSync;

use App\Services\AkeneoService;
use Illuminate\Support\Facades\Log;

class PsRestfulProductService
{
    protected AkeneoService $akeneoService;

    public function __construct(AkeneoService $akeneoService)
    {
        $this->akeneoService = $akeneoService;
    }

    /**
     * Fetch all products from Akeneo PIM API
     *
     * @param array $params
     * @return array
     */
    public function fetchProducts(array $params = []): array
    {
        $defaultParams = [
            'limit' => 100,
        ];

        $queryParams = array_merge($defaultParams, $params);
        
        try {
            $result = $this->akeneoService->getProductsExpanded($queryParams);
            
            if (!$result['success']) {
                Log::error('Failed to fetch products from Akeneo', [
                    'error' => $result['error'] ?? 'Unknown error',
                ]);
                
                return [
                    'success' => false,
                    'error' => $result['error'] ?? 'Unknown error',
                    'products' => [],
                ];
            }
            
            $products = $result['data']['results'] ?? [];
            $normalizedProducts = array_map(
                fn($product) => $this->normalizeProduct($product),
                $products
            );
            
            return [
                'success' => true,
                'products' => $normalizedProducts,
                'total_count' => $result['data']['count'] ?? count($normalizedProducts),
            ];
            
        } catch (\Exception $e) {
            Log::error('Exception fetching Akeneo products', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'products' => [],
            ];
        }
    }

    /**
     * Fetch a single product from Akeneo PIM API
     *
     * @param string $productId Product identifier (SKU) or UUID
     * @return array|null
     */
    public function fetchProduct(string $productId): ?array
    {
        try {
            // Try fetching by identifier first
            $result = $this->akeneoService->getProductByIdentifier($productId);
            
            // If not found and looks like UUID, try UUID endpoint
            if (!$result['success'] && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $productId)) {
                $result = $this->akeneoService->getProductByUuid($productId);
            }
            
            if (!$result['success']) {
                Log::error('Failed to fetch product from Akeneo', [
                    'product_id' => $productId,
                    'error' => $result['error'] ?? 'Unknown error',
                ]);
                
                return null;
            }
            
            return $this->normalizeProduct($result['data']);
            
        } catch (\Exception $e) {
            Log::error('Exception fetching Akeneo product', [
                'product_id' => $productId,
                'message' => $e->getMessage(),
            ]);
            
            return null;
        }
    }

    /**
     * Normalize Akeneo product data to standard format
     *
     * @param array $product
     * @return array
     */
    protected function normalizeProduct(array $product): array
    {
        $values = $product['values'] ?? [];
        
        // Extract description (already extracted by AkeneoService)
        $description = $product['description'] ?? $this->getProductValue($values, 'long_description', 'en_US');
        
        // SKU already extracted by AkeneoService from values.sku
        $sku = $product['sku'] ?? null;
        
        // Extract images
        $imageUrls = [];
        
        // Get all images if available
        if (!empty($product['allImageUrls'])) {
            $imageUrls = $product['allImageUrls'];
        } elseif ($product['primaryImageUrl'] ?? null) {
            $imageUrls[] = $product['primaryImageUrl'];
        }
        
        // Extract tags from categories
        $tags = $product['categories'] ?? [];
        if ($product['family']) {
            $tags[] = $product['family'];
        }

        // Extract price from values
        $price = $this->getProductValue($values, 'price', 'en_US');
        if (is_array($price)) {
            $price = $price[0]['amount'] ?? 0;
        }
        
        // Get proper product title (not UUID)
        $title = $product['name'] ?? $sku ?? 'Untitled Product';
        $vendor = $product['brandName'] ?? 'Akeneo';
        
        Log::info('Normalized Product for Sync', [
            'ps_product_id' => $product['uuid'],
            'title' => $title,
            'sku' => $sku,
            'vendor' => $vendor,
            'product_type' => $product['family'],
            'has_description' => !empty($description),
            'has_images' => count($imageUrls) > 0,
        ]);

        return [
            'ps_product_id' => $product['uuid'] ?? $product['identifier'] ?? null,
            'title' => $this->normalizeString($title),
            'sku' => $sku,
            'description' => $this->normalizeString($description),
            'vendor' => $this->normalizeString($vendor),
            'product_type' => $this->normalizeString($product['family'] ?? null),
            'price' => $this->normalizePrice($price ?? $product['listPrice'] ?? 0),
            'compare_at_price' => null,
            'inventory_quantity' => (int) ($this->getProductValue($values, 'quantity') ?? 0),
            'status' => ($product['enabled'] ?? true) ? 'active' : 'draft',
            'tags' => $tags,
            'image_urls' => $imageUrls,
            'raw_payload' => $product,
        ];
    }

    /**
     * Get product value from Akeneo values array
     *
     * @param array $values
     * @param string $attribute
     * @param string $locale
     * @return mixed
     */
    protected function getProductValue(array $values, string $attribute, string $locale = 'en_US')
    {
        if (!isset($values[$attribute])) {
            return null;
        }

        $attributeValues = $values[$attribute];

        foreach ($attributeValues as $value) {
            // Return localized value if available
            if (isset($value['locale']) && $value['locale'] === $locale) {
                return $value['data'] ?? null;
            }
            // Return non-localized value (for simple attributes)
            if (!isset($value['locale'])) {
                return $value['data'] ?? null;
            }
        }

        // Fallback to first value
        return $attributeValues[0]['data'] ?? null;
    }

    /**
     * Normalize string value
     */
    protected function normalizeString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        
        return trim($value);
    }

    /**
     * Normalize price value
     */
    protected function normalizePrice($value): float
    {
        if ($value === null) {
            return 0.0;
        }
        
        return (float) $value;
    }

    /**
     * Normalize status value
     */
    protected function normalizeStatus(?string $status): string
    {
        if ($status === null) {
            return 'active';
        }
        
        $status = strtolower(trim($status));
        
        return in_array($status, ['active', 'draft', 'archived']) ? $status : 'active';
    }
}
