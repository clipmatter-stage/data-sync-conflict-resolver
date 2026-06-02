# Akeneo to Shopify Sync - Optimization & Variant Support Plan

## Overview
This document outlines the strategy to make the product sync process fast, maintainable, and properly handle product variants.

---

## 🎯 KEY IMPROVEMENTS

### 1. **Product Variants Support**

#### Current State
- ✗ All products treated as simple products
- ✗ No variant hierarchy
- ✗ No options/attributes mapping

#### Target State
- ✓ Product Models → Shopify Products
- ✓ Variant Products → Shopify Variants
- ✓ Family Variant attributes → Shopify Options

#### Implementation

**Akeneo Structure:**
```
Root Product Model (jack)
  └── Sub Product Model (jack_blue)
      ├── Variant Product (jack_blue_s)
      ├── Variant Product (jack_blue_m)
      └── Variant Product (jack_blue_l)
```

**Shopify Structure:**
```
Product (Jack T-Shirt)
  Options: [Color, Size]
  ├── Variant (Blue / Small)
  ├── Variant (Blue / Medium)
  └── Variant (Blue / Large)
```

---

### 2. **Bulk Operations for Performance**

#### Akeneo API Best Practices
- Use `pagination_type=search_after` for products (NOT offset)
- Limit: 100 items per request (maximum allowed)
- Rate limit: 100 requests/second, 4 concurrent per connection
- Implement exponential backoff with jitter

#### Shopify Bulk GraphQL Mutations
Replace sequential operations with:
- `productCreate` - Create product with initial variant
- `productVariantsBulkCreate` - Add up to 2,048 variants at once
- `productVariantsBulkUpdate` - Update up to 2,048 variants at once
- `bulkOperationRunMutation` - Run mutations asynchronously
- Subscribe to `bulk_operations/finish` webhook

---

### 3. **Incremental Sync Strategy**

#### Phase 1: Full Sync (Initial)
1. Fetch all Akeneo Product Models
2. Fetch all Akeneo Variant Products
3. Group variants by parent
4. Bulk create in Shopify

#### Phase 2: Incremental Updates
Track changes using:
- Akeneo `updated` timestamp
- Store `last_sync_timestamp` in database
- Only fetch products updated after last sync
- Use Akeneo filters: `updated={"operator":"GREATER THAN","value":"2026-05-19T00:00:00+00:00"}`

---

### 4. **Webhook Integration**

#### Shopify → Akeneo (Optional)
Subscribe to Shopify webhooks:
- `products/create`
- `products/update`
- `products/delete`
- `inventory_levels/update`

#### Akeneo → Shopify (Recommended)
Akeneo Enterprise Edition has webhook feature:
- Product updated events
- Product model updated events
- Trigger incremental sync on webhook receipt

---

## 📋 IMPLEMENTATION PHASES

### Phase 1: Variant Support (Week 1)

**1.1 Database Schema Updates**
```sql
-- Add variant tracking
ALTER TABLE products ADD COLUMN is_product_model BOOLEAN DEFAULT FALSE;
ALTER TABLE products ADD COLUMN parent_code VARCHAR(255) NULL;
ALTER TABLE products ADD COLUMN variant_axis_1 VARCHAR(100) NULL;
ALTER TABLE products ADD COLUMN variant_axis_2 VARCHAR(100) NULL;
ALTER TABLE products ADD COLUMN variant_axis_3 VARCHAR(100) NULL;
ALTER TABLE products ADD COLUMN shopify_variant_id VARCHAR(255) NULL;
```

**1.2 Akeneo Service Updates**
- Add `getProductModels()` method
- Add `getVariantProducts($parentCode)` method
- Parse family variant structure
- Extract variant axes (color, size, etc.)

**1.3 Shopify Service Updates**
- Implement `productVariantsBulkCreate` mutation
- Implement `productVariantsBulkUpdate` mutation
- Map Akeneo variant axes to Shopify options

**1.4 Sync Logic Updates**
- Detect Product Models vs Products
- Group variants by parent
- Create Product with all variants in one operation

---

### Phase 2: Bulk Operations (Week 2)

**2.1 Batch Processing**
```php
// Process in batches of 2,000 variants
$batchSize = 2000;
$variants = array_chunk($allVariants, $batchSize);

foreach ($variants as $batch) {
    productVariantsBulkCreate($productId, $batch);
}
```

**2.2 Akeneo Pagination**
```php
// Use search_after for better performance
$url = '/api/rest/v1/products-uuid?pagination_type=search_after&limit=100';

while ($url) {
    $response = $this->makeRequest('GET', $url);
    $products = $response['data']['_embedded']['items'];
    
    // Process products...
    
    $url = $response['data']['_links']['next']['href'] ?? null;
}
```

**2.3 Rate Limit Handling**
```php
// Exponential backoff with jitter
$retryDelay = 1; // seconds
$maxRetries = 5;

for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
    try {
        $response = $this->makeRequest($method, $endpoint);
        break;
    } catch (RateLimitException $e) {
        if ($attempt === $maxRetries - 1) throw $e;
        
        $jitter = rand(0, 1000) / 1000; // 0-1 second
        sleep($retryDelay + $jitter);
        $retryDelay *= 2; // Exponential backoff
    }
}
```

---

### Phase 3: Incremental Sync (Week 3)

**3.1 Timestamp Tracking**
```php
// Store last sync time
$lastSync = Setting::get('last_product_sync_timestamp');

// Fetch only updated products
$filter = [
    'updated' => [
        'operator' => 'GREATER THAN',
        'value' => $lastSync
    ]
];

$updatedProducts = $akeneo->getProducts($filter);

// Update timestamp after successful sync
Setting::set('last_product_sync_timestamp', now());
```

**3.2 Delta Detection**
- Compare Akeneo `updated` vs local `updated_at`
- Only sync changed products
- Track which fields changed to minimize Shopify API calls

---

### Phase 4: Webhook Integration (Week 4)

**4.1 Shopify Webhook Handler**
```php
Route::post('/webhooks/shopify/products/update', [WebhookController::class, 'handleProductUpdate']);

// Verify webhook authenticity
// Update local database
// Optionally sync back to Akeneo
```

**4.2 Bulk Operation Webhook**
```php
Route::post('/webhooks/shopify/bulk-operations/finish', function (Request $request) {
    $operationId = $request->input('admin_graphql_api_id');
    $status = $request->input('status'); // COMPLETED, FAILED, CANCELED
    
    // Download results and process
    BulkOperationResultJob::dispatch($operationId, $status);
});
```

---

## 🔧 CODE STRUCTURE

### New Files to Create

```
app/
  Services/
    ProductSync/
      VariantSyncService.php          # NEW: Handle variant logic
      BulkOperationService.php         # NEW: Shopify bulk ops
      IncrementalSyncService.php       # NEW: Delta sync
  Http/
    Controllers/
      WebhookController.php            # NEW: Webhook handlers
  Jobs/
    ProcessBulkOperationJob.php        # NEW: Async bulk processing
    IncrementalProductSyncJob.php      # NEW: Delta sync job
  Models/
    SyncTimestamp.php                  # NEW: Track sync times
    BulkOperation.php                  # NEW: Track bulk ops
database/
  migrations/
    add_variant_support_to_products.php
config/
  sync.php                             # NEW: Sync configuration
```

---

## 📊 PERFORMANCE IMPROVEMENTS

### Current Performance
- 25 products in ~3 minutes
- Sequential API calls
- ~7 seconds per product

### Target Performance (After Optimization)
- 1,000 products in ~5 minutes
- Batch processing
- ~0.3 seconds per product
- **~20x faster**

### Optimizations
1. **Akeneo search_after pagination**: 3x faster than offset
2. **Bulk variant creation**: 50x faster than individual
3. **Concurrent requests**: 4x throughput (4 concurrent)
4. **Incremental sync**: Only sync changed products (90% reduction in typical runs)
5. **Response caching**: OAuth tokens cached for 58 minutes

---

## 🎯 VARIANT MAPPING EXAMPLES

### Example 1: Simple Variants (1 level)

**Akeneo:**
```json
{
  "type": "product_model",
  "code": "tshirt_basic",
  "family_variant": "clothing_by_size",
  "values": {
    "product_name": [{"data": "Basic T-Shirt"}],
    "brand": [{"data": "BrandX"}]
  }
}

// Variants
{
  "uuid": "xxx-s",
  "parent": "tshirt_basic",
  "values": {
    "sku": [{"data": "TSH-S"}],
    "size": [{"data": "S"}]
  }
}
```

**Shopify:**
```graphql
mutation {
  productCreate(input: {
    title: "Basic T-Shirt"
    vendor: "BrandX"
    productOptions: [{name: "Size", values: ["S", "M", "L"]}]
  }) {
    product { id }
  }
}

mutation {
  productVariantsBulkCreate(productId: "gid://...", variants: [
    {optionValues: [{optionName: "Size", name: "S"}], inventoryItem: {sku: "TSH-S"}},
    {optionValues: [{optionName: "Size", name: "M"}], inventoryItem: {sku: "TSH-M"}},
    {optionValues: [{optionName: "Size", name: "L"}], inventoryItem: {sku: "TSH-L"}}
  ])
}
```

### Example 2: Complex Variants (2 levels)

**Akeneo:**
```
Product Model: tshirt_premium (Common: name, brand, description)
  └── Sub Model: tshirt_premium_blue (Color level: images, composition)
      ├── Variant: blue_s (Size level: SKU, EAN, price, inventory)
      ├── Variant: blue_m
      └── Variant: blue_l
  └── Sub Model: tshirt_premium_red
      ├── Variant: red_s
      ├── Variant: red_m
      └── Variant: red_l
```

**Shopify:**
```graphql
mutation {
  productCreate(input: {
    title: "Premium T-Shirt"
    vendor: "BrandX"
    productOptions: [
      {name: "Color", values: ["Blue", "Red"]},
      {name: "Size", values: ["S", "M", "L"]}
    ]
  })
}

mutation {
  productVariantsBulkCreate(productId: "gid://...", variants: [
    {
      optionValues: [
        {optionName: "Color", name: "Blue"},
        {optionName: "Size", name: "S"}
      ],
      inventoryItem: {sku: "PREM-BLU-S"},
      price: "29.99"
    },
    // ... 5 more variants
  ])
}
```

---

## 🛠️ MAINTENANCE STRATEGIES

### 1. Monitoring & Logging
```php
// Track sync metrics
Log::info('Sync Summary', [
    'duration_seconds' => $duration,
    'products_fetched' => $fetched,
    'products_created' => $created,
    'products_updated' => $updated,
    'variants_created' => $variantsCreated,
    'errors' => $errors,
    'api_calls_akeneo' => $akeneoApiCalls,
    'api_calls_shopify' => $shopifyApiCalls
]);
```

### 2. Error Recovery
- Store failed products in `failed_syncs` table
- Retry failed items separately
- Alert on repeated failures
- Graceful degradation (skip problematic products)

### 3. Data Validation
- Validate variant structure before API calls
- Check for duplicate SKUs
- Verify option combinations are valid
- Validate required fields exist

### 4. Testing Strategy
- Unit tests for variant mapping
- Integration tests with sandbox accounts
- Load testing with 10,000+ products
- Rollback mechanism for failed syncs

---

## 🚨 CRITICAL CONSIDERATIONS

### Akeneo Limits
- **Max 100 items per API request**
- **100 requests/second globally**
- **4 concurrent requests per connection**
- **10 concurrent requests per instance**
- HTTP 429 on over-usage (implement exponential backoff)

### Shopify Limits
- **Max 2,048 variants per bulk operation**
- **Max 100 variants per product** (Shopify platform limit)
- **Max 3 options per product**
- GraphQL cost-based rate limiting (monitor `extensions.cost`)

### Data Integrity
- Shopify variants require at least 1 option
- Cannot have variants with identical option combinations
- SKU must be unique across all products (if provided)
- Handle orphaned variants (parent deleted in Akeneo)

---

## 📅 ROLLOUT TIMELINE

| Week | Phase | Deliverables |
|------|-------|--------------|
| 1 | Variant Support | Database schema, variant detection, basic mapping |
| 2 | Bulk Operations | Batch processing, rate limiting, exponential backoff |
| 3 | Incremental Sync | Timestamp tracking, delta detection, filters |
| 4 | Webhooks | Webhook handlers, bulk operation tracking |
| 5 | Testing & Optimization | Load testing, performance tuning, documentation |

---

## 🎓 BEST PRACTICES

### Akeneo
1. Always use `pagination_type=search_after` for products
2. Fetch product models first, then variants
3. Use filters to reduce data transfer
4. Cache family variant structures
5. Implement circuit breaker for 429 responses

### Shopify
1. Batch variant operations (2,000 at a time)
2. Use bulk mutations for large datasets
3. Subscribe to webhooks instead of polling
4. Query only needed fields to reduce costs
5. Convert ResponseAccess to arrays immediately

### General
1. Process product models before variants
2. Group variants by parent for efficient creation
3. Store Shopify IDs for all entities (products, variants, options)
4. Implement idempotent operations (safe to retry)
5. Log everything for debugging

---

## 📚 REFERENCE LINKS

### Akeneo
- API Documentation: https://api.akeneo.com/documentation/introduction.html
- Pagination: https://api.akeneo.com/documentation/pagination.html
- Good Practices: https://api.akeneo.com/documentation/good-practices.html
- Product Variants: https://help.akeneo.com/pim/serenity/articles/what-about-products-variants.html

### Shopify
- GraphQL Admin API: https://shopify.dev/docs/api/admin-graphql
- Bulk Operations: https://shopify.dev/docs/api/usage/bulk-operations/imports
- Product Variants: https://shopify.dev/docs/apps/build/product-merchandising/products-and-collections
- Webhooks: https://shopify.dev/docs/apps/build/webhooks

---

## ✅ NEXT STEPS

1. **Review this plan** with your team
2. **Create a backup** of current database
3. **Set up staging environment** for testing
4. **Start with Phase 1** (Variant Support)
5. **Test thoroughly** with sample Akeneo product models
6. **Monitor performance** metrics after each phase
7. **Document lessons learned** for future reference

---

**Questions? Need clarification on any section?** Let me know!
