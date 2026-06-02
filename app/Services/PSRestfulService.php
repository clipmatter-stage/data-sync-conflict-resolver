<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * @deprecated This service is no longer used. Use AkeneoService instead.
 * PS RESTful API integration has been replaced with Akeneo PIM.
 * This class is kept for reference only.
 */
class PSRestfulService
{
    protected string $apiKey;
    protected string $apiUrl;
    protected int $timeout;

    public function __construct()
    {
        $this->apiKey = config('psrestful.api_key');
        $this->apiUrl = config('psrestful.api_url');
        $this->timeout = config('psrestful.timeout', 30);
    }

    /**
     * Get products from PSRestful API v2
     *
     * @param array $params Query parameters (page, page_size, supplier_code, etc.)
     * @return array
     */
    public function getProducts(array $params = []): array
    {
        try {
            // Set default parameters
            $defaultParams = [
                'page' => 1,
                'page_size' => 10,
            ];

            $queryParams = array_merge($defaultParams, $params);

            Log::info('PSRestful API Request', [
                'endpoint' => '/extra/v2/products',
                'params' => $queryParams
            ]);

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'X-API-Key' => $this->apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->get("{$this->apiUrl}/extra/v2/products", $queryParams);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('PSRestful API Response - Success', [
                    'status' => $response->status(),
                    'total_products' => $data['count'] ?? 0,
                    'page' => $queryParams['page'],
                    'page_size' => $queryParams['page_size'],
                ]);

                // Log detailed product data
                if (isset($data['results']) && is_array($data['results'])) {
                    foreach ($data['results'] as $index => $product) {
                        $this->logProductDetails($product, $index + 1);
                    }
                }

                return [
                    'success' => true,
                    'data' => $data,
                ];
            }

            Log::error('PSRestful API Response - Failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'error' => $response->body(),
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error('PSRestful API Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get products with expanded data (variants, images, inventory, etc.)
     *
     * @param array $params Query parameters
     * @param array $expand What to expand: ['medias', 'inventory', 'classifications', 'combined_ppc']
     * @return array
     */
    public function getProductsExpanded(array $params = [], array $expand = ['medias']): array
    {
        // Note: The /extra/v2/products endpoint doesn't support expand parameter
        // To get full product details with variants and images, you need to:
        // 1. Get product list from /extra/v2/products
        // 2. Then fetch individual products using getProduct() with expand parameter

        Log::info('Getting expanded product data - fetching product list first');
        
        $result = $this->getProducts($params);
        
        if (!$result['success']) {
            return $result;
        }

        $products = $result['data']['results'] ?? [];
        $expandedProducts = [];

        foreach ($products as $product) {
            $extraId = $product['extraId'] ?? null;
            if ($extraId) {
                $expandedProduct = $this->getProduct($extraId, $expand);
                if ($expandedProduct['success']) {
                    $expandedProducts[] = $expandedProduct['data'];
                }
            }
        }

        $result['data']['results'] = $expandedProducts;
        return $result;
    }

    /**
     * Get a single product by ID
     *
     * @param string|int $productId Extra ID of the product
     * @param array $expand What to expand: ['medias', 'inventory', 'classifications', 'combined_ppc']
     * @return array
     */
    public function getProduct($productId, array $expand = []): array
    {
        try {
            $queryParams = [];
            if (!empty($expand)) {
                $queryParams['expand'] = $expand;
            }

            Log::info('PSRestful API Request - Single Product', [
                'product_id' => $productId,
                'expand' => $expand
            ]);

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'X-API-Key' => $this->apiKey,
                    'Accept' => 'application/json',
                ])
                ->get("{$this->apiUrl}/extra/v2/products/{$productId}", $queryParams);

            if ($response->successful()) {
                $data = $response->json();
                $this->logProductDetails($data);

                return [
                    'success' => true,
                    'data' => $data,
                ];
            }

            Log::error('PSRestful API Response - Failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'error' => $response->body(),
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error('PSRestful API Exception', [
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Log detailed product information including variants and images
     *
     * @param array $product
     * @param int|null $index
     * @return void
     */
    protected function logProductDetails(array $product, ?int $index = null): void
    {
        $logPrefix = $index ? "Product #{$index}" : "Product";

        Log::info("=== {$logPrefix} Details ===", [
            'extra_id' => $product['extraId'] ?? 'N/A',
            'product_id' => $product['productId'] ?? 'N/A',
            'product_name' => $product['name'] ?? 'N/A',
            'description' => is_array($product['description'] ?? null) 
                ? implode(' ', $product['description']) 
                : ($product['description'] ?? 'N/A'),
            'supplier_code' => $product['supplierShortName'] ?? 'N/A',
            'supplier_name' => $product['supplierName'] ?? 'N/A',
            'brand_name' => $product['brandName'] ?? 'N/A',
            'main_category' => $product['mainCategory'] ?? 'N/A',
            'status' => $product['status'] ?? 'N/A',
            'country_of_origin' => $product['countryOfOrigin'] ?? 'N/A',
            'primary_material' => $product['primaryMaterial'] ?? 'N/A',
            'lead_time' => $product['leadTime'] ?? 'N/A',
            'list_price' => $product['listPrice'] ?? 'N/A',
            'min_qty' => $product['minQty'] ?? 'N/A',
            'is_closeout' => $product['isCloseout'] ?? false,
            'is_caution' => $product['isCaution'] ?? false,
        ]);

        // Log variants/parts
        if (isset($product['parts']) && is_array($product['parts'])) {
            Log::info("{$logPrefix} - Variants/Parts", [
                'total_variants' => count($product['parts']),
            ]);

            foreach ($product['parts'] as $partIndex => $part) {
                Log::info("{$logPrefix} - Variant #" . ($partIndex + 1), [
                    'part_id' => $part['partId'] ?? $part['part_id'] ?? 'N/A',
                    'description' => $part['description'] ?? 'N/A',
                    'color_name' => $part['colorName'] ?? $part['color_name'] ?? 'N/A',
                    'size' => $part['size'] ?? 'N/A',
                    'list_price' => $part['listPrice'] ?? $part['list_price'] ?? 'N/A',
                    'price_unit' => $part['priceUnit'] ?? $part['price_unit'] ?? 'N/A',
                    'min_quantity' => $part['minQuantity'] ?? $part['min_quantity'] ?? 'N/A',
                ]);
            }
        } elseif (isset($product['noVariants'])) {
            Log::info("{$logPrefix} - Variants Info", [
                'total_variants' => $product['noVariants'],
                'note' => 'Variants not expanded - use ?expand=parts to get full variant data',
            ]);
        }

        // Log images/media
        if (isset($product['medias']) && is_array($product['medias'])) {
            Log::info("{$logPrefix} - Images/Media", [
                'total_media' => count($product['medias']),
            ]);

            foreach ($product['medias'] as $mediaIndex => $media) {
                Log::info("{$logPrefix} - Image #" . ($mediaIndex + 1), [
                    'media_type' => $media['mediaType'] ?? $media['media_type'] ?? 'N/A',
                    'url' => $media['url'] ?? 'N/A',
                    'description' => $media['description'] ?? 'N/A',
                    'file_type' => $media['fileType'] ?? $media['file_type'] ?? 'N/A',
                    'width' => $media['width'] ?? 'N/A',
                    'height' => $media['height'] ?? 'N/A',
                ]);
            }
        } elseif (isset($product['primaryImageUrl'])) {
            Log::info("{$logPrefix} - Primary Image", [
                'primary_image_url' => $product['primaryImageUrl'],
                'note' => 'Media not expanded - use ?expand=medias to get full media data',
            ]);
        }

        // Log full product payload for detailed inspection
        Log::info("{$logPrefix} - Complete Payload", [
            'payload' => json_encode($product, JSON_PRETTY_PRINT)
        ]);
    }
}
