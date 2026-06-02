<?php

namespace App\Services\ProductSync;

use Illuminate\Support\Facades\Log;
use Osiset\ShopifyApp\Contracts\ShopModel;

class ShopifyProductService
{
    /**
     * Fetch a product from Shopify by SKU
     *
     * @param ShopModel $shop
     * @param string $sku
     * @return array|null
     */
    public function fetchProductBySku(ShopModel $shop, string $sku): ?array
    {
        try {

            // GraphQL query to find product by SKU
            $query = <<<'GRAPHQL'
            query getProductBySku($query: String!) {
                products(first: 1, query: $query) {
                    edges {
                        node {
                            id
                            title
                            description
                            vendor
                            productType
                            status
                            tags
                            variants(first: 1) {
                                edges {
                                    node {
                                        id
                                        sku
                                        price
                                        compareAtPrice
                                        inventoryQuantity
                                    }
                                }
                            }
                            images(first: 10) {
                                edges {
                                    node {
                                        url
                                    }
                                }
                            }
                        }
                    }
                }
            }
            GRAPHQL;

            $variables = [
                'query' => "sku:{$sku}",
            ];

            $response = $shop->api()->graph($query, $variables);
            
            // Convert ResponseAccess to array
            $responseArray = json_decode(json_encode($response), true);
            
            if (isset($responseArray['errors']) && !empty($responseArray['errors'])) {
                Log::error('Shopify GraphQL error fetching product by SKU', [
                    'sku' => $sku,
                    'errors' => $responseArray['errors'],
                    'response_body' => $responseArray['body'] ?? null,
                ]);
                return null;
            }

            $edges = $responseArray['body']['data']['products']['edges'] ?? [];
            
            if (empty($edges)) {
                return null;
            }

            $product = $edges[0]['node'];
            
            return $this->normalizeProduct($product);
            
        } catch (\Exception $e) {
            Log::error('Exception fetching Shopify product by SKU', [
                'sku' => $sku,
                'message' => $e->getMessage(),
            ]);
            
            return null;
        }
    }

    /**
     * Fetch a product from Shopify by Shopify Product ID
     *
     * @param ShopModel $shop
     * @param string $shopifyProductId
     * @return array|null
     */
    public function fetchProductById(ShopModel $shop, string $shopifyProductId): ?array
    {
        try {

            $query = <<<'GRAPHQL'
            query getProduct($id: ID!) {
                product(id: $id) {
                    id
                    title
                    description
                    vendor
                    productType
                    status
                    tags
                    variants(first: 1) {
                        edges {
                            node {
                                id
                                sku
                                price
                                compareAtPrice
                                inventoryQuantity
                            }
                        }
                    }
                    images(first: 10) {
                        edges {
                            node {
                                url
                            }
                        }
                    }
                }
            }
            GRAPHQL;

            $variables = [
                'id' => $shopifyProductId,
            ];

            $response = $shop->api()->graph($query, $variables);
            
            // Convert ResponseAccess to array
            $responseArray = json_decode(json_encode($response), true);
            
            if (isset($responseArray['errors']) && !empty($responseArray['errors'])) {
                Log::error('Shopify GraphQL error fetching product by ID', [
                    'id' => $shopifyProductId,
                    'errors' => $responseArray['errors'],
                    'response_body' => $responseArray['body'] ?? null,
                ]);
                return null;
            }

            $product = $responseArray['body']['data']['product'] ?? null;
            
            if (!$product) {
                return null;
            }

            return $this->normalizeProduct($product);
            
        } catch (\Exception $e) {
            Log::error('Exception fetching Shopify product by ID', [
                'id' => $shopifyProductId,
                'message' => $e->getMessage(),
            ]);
            
            return null;
        }
    }

    /**
     * Create a product in Shopify
     *
     * @param ShopModel $shop
     * @param array $productData
     * @return array|null
     */
    public function createProduct(ShopModel $shop, array $productData): ?array
    {
        try {
            // Create product with basic info first
            $mutation = <<<'GRAPHQL'
            mutation productCreate($input: ProductInput!) {
                productCreate(input: $input) {
                    product {
                        id
                        title
                        description
                        vendor
                        productType
                        status
                        tags
                        variants(first: 1) {
                            edges {
                                node {
                                    id
                                    sku
                                    price
                                    compareAtPrice
                                    inventoryQuantity
                                }
                            }
                        }
                        media(first: 10) {
                            edges {
                                node {
                                    ... on MediaImage {
                                        id
                                        image {
                                            url
                                        }
                                    }
                                }
                            }
                        }
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }
            GRAPHQL;

            $input = [
                'title' => $productData['title'],
                'descriptionHtml' => $productData['description'] ?? '',
                'vendor' => $productData['vendor'] ?? '',
                'productType' => $productData['product_type'] ?? '',
                'status' => strtoupper($productData['status'] ?? 'ACTIVE'),
                'tags' => $productData['tags'] ?? [],
            ];
            
            Log::info('Shopify productCreate mutation - Request', [
                'input' => $input,
                'sku' => $productData['sku'] ?? null,
                'price' => $productData['price'] ?? null,
            ]);

            $variables = ['input' => $input];

            $response = $shop->api()->graph($mutation, $variables);
            
            // Convert ResponseAccess to array
            $responseArray = json_decode(json_encode($response), true);
            
            Log::info('Shopify productCreate mutation - Response', [
                'has_errors' => isset($responseArray['errors']) && !empty($responseArray['errors']),
                'has_data' => isset($responseArray['body']['data']),
                'response_keys' => array_keys($responseArray),
            ]);
            
            if (isset($responseArray['errors']) && !empty($responseArray['errors'])) {
                Log::error('Shopify GraphQL error creating product', [
                    'errors' => $responseArray['errors'],
                    'input' => $input,
                ]);
                return null;
            }

            // Access the data properly
            $data = $responseArray['body']['data'] ?? null;
            if (!$data || !isset($data['productCreate'])) {
                Log::error('Shopify response missing data', ['response' => $responseArray]);
                return null;
            }
            
            $productCreate = $data['productCreate'];
            $userErrors = $productCreate['userErrors'] ?? [];
            
            if (!empty($userErrors)) {
                Log::error('Shopify user errors creating product', [
                    'errors' => $userErrors,
                    'input' => $input,
                ]);
                return null;
            }

            $product = $productCreate['product'] ?? null;
            
            if (!$product) {
                Log::error('Shopify productCreate returned null product', [
                    'productCreate' => $productCreate,
                ]);
                return null;
            }

            // Convert ResponseAccess to array
            if (is_object($product) && method_exists($product, 'toArray')) {
                $product = $product->toArray();
            }

            $productId = $product['id'];
            $variantId = $product['variants']['edges'][0]['node']['id'] ?? null;
            
            Log::info('Shopify product created successfully', [
                'shopify_product_id' => $productId,
                'shopify_variant_id' => $variantId,
                'title' => $product['title'],
            ]);
            
            // Update variant with price and SKU using productSet (modern approach)
            if ($variantId && (isset($productData['price']) || isset($productData['sku']))) {
                $this->updateProductVariant($shop, $productId, $variantId, [
                    'price' => isset($productData['price']) ? (string) $productData['price'] : null,
                    'sku' => $productData['sku'] ?? null,
                ]);
            }

            // Add media/images if provided
            if (!empty($productData['image_urls'])) {
                $this->attachProductMedia($shop, $productId, $productData['image_urls']);
            }

            // Fetch the updated product to return current state
            return $this->fetchProductById($shop, $productId);
            
        } catch (\Exception $e) {
            Log::error('Exception creating Shopify product', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $productData,
            ]);
            
            return null;
        }
    }

    /**
     * Update product variant using GraphQL productVariantsBulkUpdate mutation
     *
     * @param ShopModel $shop
     * @param string $productId
     * @param string $variantId
     * @param array $updates
     * @return bool
     */
    protected function updateProductVariant(ShopModel $shop, string $productId, string $variantId, array $updates): bool
    {
        try {
            $mutation = <<<'GRAPHQL'
            mutation productVariantsBulkUpdate($productId: ID!, $variants: [ProductVariantsBulkInput!]!) {
                productVariantsBulkUpdate(productId: $productId, variants: $variants) {
                    productVariants {
                        id
                        price
                        sku
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }
            GRAPHQL;

            $variantInput = ['id' => $variantId];

            // Set price if provided
            if (isset($updates['price']) && $updates['price'] !== null) {
                $variantInput['price'] = $updates['price'];
            }
            
            // Set SKU through inventoryItem if provided
            if (isset($updates['sku']) && $updates['sku'] !== null) {
                $variantInput['inventoryItem'] = [
                    'sku' => $updates['sku'],
                ];
            }

            $variables = [
                'productId' => $productId,
                'variants' => [$variantInput],
            ];

            $response = $shop->api()->graph($mutation, $variables);
            
            // Convert ResponseAccess to array
            $responseArray = json_decode(json_encode($response), true);
            
            if (isset($responseArray['errors']) && !empty($responseArray['errors'])) {
                Log::error('Shopify GraphQL error updating variant', [
                    'errors' => $responseArray['errors'],
                    'variables' => $variables,
                ]);
                return false;
            }

            // Check for user errors
            $data = $responseArray['body']['data'] ?? null;
            if ($data && isset($data['productVariantsBulkUpdate'])) {
                $userErrors = $data['productVariantsBulkUpdate']['userErrors'] ?? [];
                
                if (!empty($userErrors)) {
                    Log::error('Shopify user errors updating variant', [
                        'errors' => $userErrors,
                        'variables' => $variables,
                    ]);
                    return false;
                }
            }

            return true;
            
        } catch (\Exception $e) {
            Log::error('Exception updating Shopify variant', [
                'product_id' => $productId,
                'variant_id' => $variantId,
                'message' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Attach media/images to a product
     *
     * @param ShopModel $shop
     * @param string $productId
     * @param array $imageUrls
     * @return bool
     */
    protected function attachProductMedia(ShopModel $shop, string $productId, array $imageUrls): bool
    {
        try {
            $mutation = <<<'GRAPHQL'
            mutation productCreateMedia($productId: ID!, $media: [CreateMediaInput!]!) {
                productCreateMedia(productId: $productId, media: $media) {
                    media {
                        ... on MediaImage {
                            id
                            image {
                                url
                            }
                        }
                    }
                    mediaUserErrors {
                        field
                        message
                    }
                }
            }
            GRAPHQL;

            $media = [];
            foreach ($imageUrls as $imageUrl) {
                $media[] = [
                    'originalSource' => $imageUrl,
                    'mediaContentType' => 'IMAGE',
                ];
            }

            $variables = [
                'productId' => $productId,
                'media' => $media,
            ];

            $response = $shop->api()->graph($mutation, $variables);
            
            // Convert ResponseAccess to array
            $responseArray = json_decode(json_encode($response), true);
            
            if (isset($responseArray['errors']) && !empty($responseArray['errors'])) {
                Log::error('Shopify GraphQL error attaching media', [
                    'errors' => $responseArray['errors'],
                    'product_id' => $productId,
                ]);
                return false;
            }

            return true;
            
        } catch (\Exception $e) {
            Log::error('Exception attaching media to product', [
                'product_id' => $productId,
                'message' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Update a product in Shopify
     *
     * @param ShopModel $shop
     * @param string $shopifyProductId
     * @param array $updates
     * @return bool
     */
    public function updateProduct(ShopModel $shop, string $shopifyProductId, array $updates): bool
    {
        try {

            $mutation = <<<'GRAPHQL'
            mutation productUpdate($input: ProductInput!) {
                productUpdate(input: $input) {
                    product {
                        id
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }
            GRAPHQL;

            $input = [
                'id' => $shopifyProductId,
            ];

            // Add fields to update
            if (isset($updates['title'])) {
                $input['title'] = $updates['title'];
            }
            if (isset($updates['description'])) {
                $input['descriptionHtml'] = $updates['description'];
            }
            if (isset($updates['vendor'])) {
                $input['vendor'] = $updates['vendor'];
            }
            if (isset($updates['product_type'])) {
                $input['productType'] = $updates['product_type'];
            }
            if (isset($updates['status'])) {
                $input['status'] = strtoupper($updates['status']);
            }
            if (isset($updates['tags'])) {
                $input['tags'] = $updates['tags'];
            }

            $variables = [
                'input' => $input,
            ];

            $response = $shop->api()->graph($mutation, $variables);
            
            // Convert ResponseAccess to array
            $responseArray = json_decode(json_encode($response), true);
            
            if (isset($responseArray['errors']) && !empty($responseArray['errors'])) {
                Log::error('Shopify GraphQL error updating product', [
                    'errors' => $responseArray['errors'],
                    'response_body' => $responseArray['body'] ?? null,
                    'input' => $input,
                ]);
                return false;
            }

            $userErrors = $responseArray['body']['data']['productUpdate']['userErrors'] ?? [];
            
            if (!empty($userErrors)) {
                Log::error('Shopify user errors updating product', [
                    'errors' => $userErrors,
                    'input' => $input,
                ]);
                return false;
            }

            return true;
            
        } catch (\Exception $e) {
            Log::error('Exception updating Shopify product', [
                'id' => $shopifyProductId,
                'message' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Normalize Shopify product data to standard format
     *
     * @param array $product
     * @return array
     */
    protected function normalizeProduct(array $product): array
    {
        // Extract variant data
        $variant = null;
        if (isset($product['variants']['edges'][0]['node'])) {
            $variant = $product['variants']['edges'][0]['node'];
        }

        // Extract images
        $imageUrls = [];
        if (isset($product['images']['edges'])) {
            foreach ($product['images']['edges'] as $edge) {
                if (isset($edge['node']['url'])) {
                    $imageUrls[] = $edge['node']['url'];
                }
            }
        }

        return [
            'shopify_product_id' => $product['id'],
            'shopify_variant_id' => $variant['id'] ?? null,
            'title' => trim($product['title'] ?? ''),
            'sku' => $variant['sku'] ?? null,
            'description' => trim($product['description'] ?? ''),
            'vendor' => trim($product['vendor'] ?? ''),
            'product_type' => trim($product['productType'] ?? ''),
            'price' => (float) ($variant['price'] ?? 0),
            'compare_at_price' => isset($variant['compareAtPrice']) ? (float) $variant['compareAtPrice'] : null,
            'inventory_quantity' => (int) ($variant['inventoryQuantity'] ?? 0),
            'status' => strtolower($product['status'] ?? 'active'),
            'tags' => $product['tags'] ?? [],
            'image_urls' => $imageUrls,
            'raw_payload' => $product,
        ];
    }
}
