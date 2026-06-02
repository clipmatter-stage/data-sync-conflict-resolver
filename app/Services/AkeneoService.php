<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class AkeneoService
{
    protected string $apiUrl;
    protected string $clientId;
    protected string $clientSecret;
    protected string $username;
    protected string $password;
    protected int $timeout;
    protected string $tokenCacheKey;
    protected int $tokenCacheTtl;
    protected bool $useUuidEndpoint;
    protected int $defaultPageSize;

    public function __construct()
    {
        $this->apiUrl = config('akeneo.api_url');
        $this->clientId = config('akeneo.client_id');
        $this->clientSecret = config('akeneo.client_secret');
        $this->username = config('akeneo.username');
        $this->password = config('akeneo.password');
        $this->timeout = config('akeneo.timeout', 30);
        $this->tokenCacheKey = config('akeneo.token_cache_key');
        $this->tokenCacheTtl = config('akeneo.token_cache_ttl');
        $this->useUuidEndpoint = config('akeneo.use_uuid_endpoint', true);
        $this->defaultPageSize = config('akeneo.default_page_size', 100);
    }

    /**
     * Get OAuth2 access token (cached)
     *
     * @return string|null
     */
    protected function getAccessToken(): ?string
    {
        // Try to get cached token
        $cachedToken = Cache::get($this->tokenCacheKey);
        if ($cachedToken) {
            Log::debug('Akeneo: Using cached access token');
            return $cachedToken;
        }

        // Generate new token
        try {
            // Base64 encode client_id:client_secret
            $credentials = base64_encode("{$this->clientId}:{$this->clientSecret}");

            Log::info('Akeneo: Requesting new access token');

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => "Basic {$credentials}",
                ])
                ->post("{$this->apiUrl}/api/oauth/v1/token", [
                    'grant_type' => 'password',
                    'username' => $this->username,
                    'password' => $this->password,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $accessToken = $data['access_token'];

                // Cache the token
                Cache::put($this->tokenCacheKey, $accessToken, $this->tokenCacheTtl);

                Log::info('Akeneo: New access token generated and cached', [
                    'expires_in' => $data['expires_in'] ?? 'unknown',
                ]);

                return $accessToken;
            }

            Log::error('Akeneo: Failed to get access token', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Akeneo: Exception while getting access token', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Make authenticated API request
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array $queryParams Query parameters
     * @param array $body Request body
     * @return array
     */
    protected function makeRequest(string $method, string $endpoint, array $queryParams = [], array $body = []): array
    {
        $accessToken = $this->getAccessToken();

        if (!$accessToken) {
            return [
                'success' => false,
                'error' => 'Failed to obtain access token',
            ];
        }

        try {
            $request = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ]);

            $url = "{$this->apiUrl}{$endpoint}";

            Log::info('Akeneo API Request', [
                'method' => $method,
                'url' => $url,
                'query' => $queryParams,
            ]);

            $response = match (strtoupper($method)) {
                'GET' => $request->get($url, $queryParams),
                'POST' => $request->post($url, $body),
                'PATCH' => $request->patch($url, $body),
                'DELETE' => $request->delete($url),
                default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
            };

            if ($response->successful()) {
                Log::info('Akeneo API Response - Success', [
                    'status' => $response->status(),
                ]);

                return [
                    'success' => true,
                    'data' => $response->json(),
                    'status' => $response->status(),
                ];
            }

            // Handle 401 Unauthorized - token might have expired
            if ($response->status() === 401) {
                Log::warning('Akeneo: Access token expired, clearing cache');
                Cache::forget($this->tokenCacheKey);
            }

            Log::error('Akeneo API Response - Failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'error' => $response->body(),
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error('Akeneo API Exception', [
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
     * Get all products with pagination
     *
     * @param array $params Query parameters (limit, search, etc.)
     * @return array
     */
    public function getProducts(array $params = []): array
    {
        $endpoint = $this->useUuidEndpoint
            ? '/api/rest/v1/products-uuid'
            : '/api/rest/v1/products';

        $queryParams = array_merge([
            'limit' => $this->defaultPageSize,
        ], $params);

        $result = $this->makeRequest('GET', $endpoint, $queryParams);

        if ($result['success'] && isset($result['data']['_embedded']['items'])) {
            $products = $result['data']['_embedded']['items'];

            Log::info('Akeneo: Retrieved products', [
                'count' => count($products),
                'total' => $result['data']['items_count'] ?? count($products),
            ]);

            return [
                'success' => true,
                'data' => [
                    'results' => $products,
                    'count' => $result['data']['items_count'] ?? count($products),
                    '_links' => $result['data']['_links'] ?? [],
                ],
            ];
        }

        return $result;
    }

    /**
     * Get a single product by identifier (SKU)
     *
     * @param string $identifier Product identifier/SKU
     * @return array
     */
    public function getProductByIdentifier(string $identifier): array
    {
        $endpoint = "/api/rest/v1/products/{$identifier}";

        return $this->makeRequest('GET', $endpoint);
    }

    /**
     * Get a single product by UUID
     *
     * @param string $uuid Product UUID
     * @return array
     */
    public function getProductByUuid(string $uuid): array
    {
        $endpoint = "/api/rest/v1/products-uuid/{$uuid}";

        return $this->makeRequest('GET', $endpoint);
    }

    /**
     * Create a new product
     *
     * @param array $productData Product data
     * @return array
     */
    public function createProduct(array $productData): array
    {
        $endpoint = $this->useUuidEndpoint
            ? '/api/rest/v1/products-uuid'
            : '/api/rest/v1/products';

        return $this->makeRequest('POST', $endpoint, [], $productData);
    }

    /**
     * Update an existing product by identifier
     *
     * @param string $identifier Product identifier
     * @param array $productData Product data to update
     * @return array
     */
    public function updateProduct(string $identifier, array $productData): array
    {
        $endpoint = "/api/rest/v1/products/{$identifier}";

        return $this->makeRequest('PATCH', $endpoint, [], $productData);
    }

    /**
     * Update an existing product by UUID
     *
     * @param string $uuid Product UUID
     * @param array $productData Product data to update
     * @return array
     */
    public function updateProductByUuid(string $uuid, array $productData): array
    {
        $endpoint = "/api/rest/v1/products-uuid/{$uuid}";

        return $this->makeRequest('PATCH', $endpoint, [], $productData);
    }

    /**
     * Get products with expanded information (compatible format)
     *
     * @param array $params
     * @return array
     */
    public function getProductsExpanded(array $params = []): array
    {
        // Get basic products first
        $result = $this->getProducts($params);

        if (!$result['success']) {
            return $result;
        }

        // Akeneo products already include most data we need
        // Map Akeneo product structure to match expected format
        $products = collect($result['data']['results'])->map(function ($product) {
            return $this->mapAkeneoProductToExpectedFormat($product);
        })->toArray();

        return [
            'success' => true,
            'data' => [
                'results' => $products,
                'count' => $result['data']['count'],
                '_links' => $result['data']['_links'] ?? [],
            ],
        ];
    }

    /**
     * Map Akeneo product structure to expected format
     *
     * @param array $akeneoProduct
     * @return array
     */
    protected function mapAkeneoProductToExpectedFormat(array $akeneoProduct): array
    {
        $values = $akeneoProduct['values'] ?? [];
        
        // UUID endpoint doesn't have 'identifier', only 'uuid'
        $identifier = $akeneoProduct['identifier'] ?? null;
        $uuid = $akeneoProduct['uuid'] ?? null;
        $productId = $identifier ?? $uuid;
        
        // Extract actual SKU from values.sku attribute
        $sku = $this->getLocalizedValue($values, 'sku') ?? $identifier ?? $uuid;
        
        // Get product name from values.product_name or values.att_name (localized)
        $name = $this->getLocalizedValue($values, 'product_name') 
             ?? $this->getLocalizedValue($values, 'att_name')
             ?? $sku
             ?? 'Untitled Product';
        
        // Get description from long_description or short_description
        $description = $this->getLocalizedValue($values, 'long_description')
                    ?? $this->getLocalizedValue($values, 'short_description');
        
        // Get brand/vendor
        $brand = $this->getLocalizedValue($values, 'brand');
        
        Log::info('Akeneo Product Mapping', [
            'uuid' => $uuid,
            'sku' => $sku,
            'name' => $name,
            'brand' => $brand,
            'family' => $akeneoProduct['family'] ?? null,
            'has_values' => !empty($values),
            'available_attributes' => array_keys($values),
        ]);

        return [
            'productId' => $productId,
            'name' => $name,
            'sku' => $sku,
            'uuid' => $uuid,
            'identifier' => $identifier,
            'enabled' => $akeneoProduct['enabled'] ?? true,
            'family' => $akeneoProduct['family'] ?? null,
            'categories' => $akeneoProduct['categories'] ?? [],
            'groups' => $akeneoProduct['groups'] ?? [],
            'values' => $values,
            'created' => $akeneoProduct['created'] ?? null,
            'updated' => $akeneoProduct['updated'] ?? null,
            'associations' => $akeneoProduct['associations'] ?? [],
            
            // Additional fields for compatibility
            'primaryImageUrl' => $this->extractPrimaryImage($values),
            'allImageUrls' => $this->extractAllImages($values),
            'listPrice' => $this->getLocalizedValue($values, 'price'),
            'description' => $description,
            'brandName' => $brand,
        ];
    }

    /**
     * Get localized value from product values
     *
     * @param array $values
     * @param string $attribute
     * @param string $locale
     * @param string $scope
     * @return mixed
     */
    protected function getLocalizedValue(array $values, string $attribute, string $locale = 'en_US', string $scope = null)
    {
        if (!isset($values[$attribute])) {
            return null;
        }

        $attributeValues = $values[$attribute];

        foreach ($attributeValues as $value) {
            if (isset($value['locale']) && $value['locale'] === $locale) {
                if ($scope && isset($value['scope']) && $value['scope'] !== $scope) {
                    continue;
                }
                return $value['data'] ?? null;
            }
        }

        // Fallback to first value
        return $attributeValues[0]['data'] ?? null;
    }

    /**
     * Extract primary image URL from product values
     *
     * @param array $values
     * @return string|null
     */
    protected function extractPrimaryImage(array $values): ?string
    {
        // Check for imported_assets (Akeneo Asset Manager)
        if (isset($values['imported_assets'])) {
            $assetData = $values['imported_assets'][0]['data'] ?? null;
            
            if (is_array($assetData) && !empty($assetData)) {
                // Get first asset code
                $assetCode = $assetData[0];
                
                // Fetch asset details to get image URL
                $assetUrl = $this->getAssetUrl($assetCode);
                if ($assetUrl) {
                    return $assetUrl;
                }
            }
        }
        
        // Look for common image attribute names
        $imageAttributes = ['image', 'picture', 'main_image', 'primary_image'];

        foreach ($imageAttributes as $attr) {
            if (isset($values[$attr])) {
                $imageValue = $values[$attr][0]['data'] ?? null;
                if ($imageValue) {
                    // Akeneo stores image references, you may need to build full URL
                    return $this->apiUrl . '/api/rest/v1/media-files/' . $imageValue . '/download';
                }
            }
        }

        return null;
    }
    
    /**
     * Extract all image URLs from product values
     *
     * @param array $values
     * @return array
     */
    protected function extractAllImages(array $values): array
    {
        $imageUrls = [];
        
        // Check for imported_assets (Akeneo Asset Manager)
        if (isset($values['imported_assets'])) {
            $assetData = $values['imported_assets'][0]['data'] ?? null;
            
            if (is_array($assetData) && !empty($assetData)) {
                // Get all asset codes
                foreach ($assetData as $assetCode) {
                    $assetUrl = $this->getAssetUrl($assetCode);
                    if ($assetUrl) {
                        $imageUrls[] = $assetUrl;
                    }
                }
            }
        }
        
        return $imageUrls;
    }
    
    /**
     * Get asset URL from Akeneo Asset Manager
     *
     * @param string $assetCode
     * @return string|null
     */
    protected function getAssetUrl(string $assetCode): ?string
    {
        try {
            // Fetch asset from Asset Family API (trial instances use asset families, not asset-manager)
            $result = $this->makeRequest('GET', "/api/rest/v1/asset-families/imported_assets/assets/{$assetCode}");
            
            if (!$result['success']) {
                Log::warning('Failed to fetch Akeneo asset', [
                    'asset_code' => $assetCode,
                    'error' => $result['error'] ?? 'Unknown',
                ]);
                return null;
            }
            
            $asset = $result['data'];
            
            // Get reference file from values (media_link attribute or similar)
            $values = $asset['values'] ?? [];
            
            // Check for media_link attribute (common in asset families)
            if (isset($values['media_link'])) {
                $mediaData = $values['media_link'][0]['data'] ?? null;
                if ($mediaData) {
                    // If it's already a full URL, return it directly
                    if (filter_var($mediaData, FILTER_VALIDATE_URL)) {
                        return $mediaData;
                    }
                    
                    // Otherwise build download URL for asset family media
                    return "{$this->apiUrl}/api/rest/v1/asset-families/imported_assets/assets/{$assetCode}/media-files/{$mediaData}/download";
                }
            }
            
            // Check other common media attribute names
            $mediaAttributes = ['media', 'file', 'image'];
            
            foreach ($mediaAttributes as $attr) {
                if (isset($values[$attr])) {
                    $mediaData = $values[$attr][0]['data'] ?? null;
                    if ($mediaData) {
                        // If it's already a full URL, return it
                        if (filter_var($mediaData, FILTER_VALIDATE_URL)) {
                            return $mediaData;
                        }
                        
                        // Otherwise build download URL
                        return "{$this->apiUrl}/api/rest/v1/asset-families/imported_assets/assets/{$assetCode}/media-files/{$mediaData}/download";
                    }
                }
            }
            
            Log::debug('Akeneo asset has no media file', [
                'asset_code' => $assetCode,
                'asset_values' => array_keys($values),
            ]);
            
            return null;
            
        } catch (\Exception $e) {
            Log::error('Exception fetching Akeneo asset URL', [
                'asset_code' => $assetCode,
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Test connection to Akeneo API
     *
     * @return array
     */
    public function testConnection(): array
    {
        $result = $this->makeRequest('GET', '/api/rest/v1');

        if ($result['success']) {
            return [
                'success' => true,
                'message' => 'Successfully connected to Akeneo PIM',
                'endpoints' => $result['data'] ?? [],
            ];
        }

        return $result;
    }
}
