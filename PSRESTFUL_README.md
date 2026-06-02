# PSRestful API Integration

This Laravel application integrates with the PSRestful API to fetch promotional products data including variants and images.

## Setup

### 1. Environment Configuration

The API credentials are configured in `.env`:

```env
PSRESTFUL_API_KEY=5deea7c43c49e6e6631f31ad2369874d
PSRESTFUL_API_URL=https://api.psrestful.com
```

### 2. Configuration File

Configuration is stored in `config/psrestful.php`.

## Usage

### Command Line Testing

Test the API connection and fetch sample products:

```bash
# Fetch 5 products (default)
php artisan psrestful:test

# Fetch 10 products
php artisan psrestful:test --page_size=10

# Fetch page 2
php artisan psrestful:test --page=2 --page_size=20
```

### HTTP Endpoints

#### 1. Test Connection
```
GET /psrestful/test
```

Returns basic connection status and sample data.

#### 2. Fetch Products
```
GET /psrestful/products?page=1&page_size=10
```

**Query Parameters:**
- `page` - Page number (default: 1)
- `page_size` - Items per page (default: 10, max: 100)
- `supplier_code` - Filter by supplier code (e.g., "HIT", "SANMAR")
- `product_id` - Filter by product ID
- `brand` - Filter by brand ID
- `status` - Filter by status ("active", "closeout", etc.)
- `search` - Full-text search

**Example:**
```bash
curl "http://your-app.test/psrestful/products?page=1&page_size=5"
```

#### 3. Fetch Single Product
```
GET /psrestful/products/{extraId}
```

**Example:**
```bash
curl "http://your-app.test/psrestful/products/123"
```

### Programmatic Usage

```php
use App\Services\PSRestfulService;

$service = app(PSRestfulService::class);

// Get products with pagination
$result = $service->getProducts([
    'page' => 1,
    'page_size' => 10,
    'supplier_code' => 'HIT',
]);

if ($result['success']) {
    $products = $result['data']['results'];
    $totalCount = $result['data']['count'];
    
    foreach ($products as $product) {
        echo $product['name'] . "\n";
        echo "Extra ID: " . $product['extraId'] . "\n";
        echo "Product ID: " . $product['productId'] . "\n";
        echo "Variants: " . $product['noVariants'] . "\n";
    }
}

// Get single product with expanded data
$result = $service->getProduct(123, ['medias', 'inventory']);

if ($result['success']) {
    $product = $result['data'];
    
    // Access media/images
    if (isset($product['medias'])) {
        foreach ($product['medias'] as $media) {
            echo $media['url'] . "\n";
        }
    }
}

// Get products with full variants and images
$result = $service->getProductsExpanded(
    ['page' => 1, 'page_size' => 5],
    ['medias', 'inventory']
);
```

## Data Structure

### Product Fields (camelCase)

- `extraId` - Internal PSRestful ID
- `productId` - Supplier's product ID
- `name` - Product name
- `description` - Product description (array or string)
- `supplierId` - Supplier ID
- `supplierName` - Supplier full name
- `supplierShortName` - Supplier code (e.g., "HIT")
- `brandName` - Brand name
- `listPrice` - List price
- `minQty` - Minimum order quantity
- `countryOfOrigin` - Country code (e.g., "CN")
- `primaryMaterial` - Material description
- `leadTime` - Lead time in days
- `status` - Status ("active", "closeout", etc.)
- `isCloseout` - Boolean closeout flag
- `isCaution` - Boolean caution flag
- `isRushService` - Rush service available
- `mainCategory` - Main category path
- `normalizedSubcategory` - Normalized category object
- `primaryImageUrl` - Primary product image URL
- `noVariants` - Number of variants (if not expanded)

### Expanded Data

To get full variants and images, use the `expand` parameter:

- `medias` - Full media/image data
- `inventory` - Real-time inventory levels
- `classifications` - Product classifications
- `combined_ppc` - Pricing and configuration

## Logging

All API requests and responses are logged to `storage/logs/laravel.log`:

- Request parameters
- Response status
- Product details including:
  - Basic info (name, ID, supplier, brand)
  - Variants count
  - Images/media
  - Complete JSON payload

**View logs:**
```bash
# Windows
Get-Content storage\logs\laravel.log -Tail 100

# Linux/Mac
tail -f storage/logs/laravel.log
```

## API Filters

### Common Filters

```php
$params = [
    'page' => 1,
    'page_size' => 10,
    
    // Supplier filters
    'supplier_code' => 'HIT',      // Supplier code
    'supplier' => 628,              // Supplier ID
    
    // Product filters
    'product_id' => '12847',        // Specific product
    'brand' => 123,                 // Brand ID
    'status' => 'active',           // active, closeout
    
    // Categories
    'main_category' => 'Bags',
    'normalized_category_id' => 5,
    'normalized_subcategory_id' => 41,
    
    // Product attributes
    'country_of_origin' => 'US',
    'primary_material' => 'Cotton',
    'lead_time' => 7,
    'lead_time__range' => '5-10',  // Range filter
    'min_qty__range' => '50-100',
    'list_price__range' => '10-50',
    
    // Flags
    'is_closeout' => true,
    'is_caution' => false,
    'is_rush_service' => true,
    'is_on_demand' => true,
    
    // Apparel
    'apparel_style' => 'Unisex',   // Unisex, Mens, Womens, Youth, etc.
    
    // Search & Sort
    'search' => 'bottle',           // Full-text search
    'ordering' => '-leadTime',      // Sort (prefix with - for desc)
];

$result = $service->getProducts($params);
```

## Rate Limiting

PSRestful API has rate limits. Be mindful when making bulk requests. Consider:
- Implementing delays between requests
- Caching responses
- Using pagination effectively

## Error Handling

```php
$result = $service->getProducts(['page' => 1]);

if (!$result['success']) {
    $errorMessage = $result['error'];
    $statusCode = $result['status'] ?? null;
    
    // Handle error
    Log::error('PSRestful API Error', [
        'error' => $errorMessage,
        'status' => $statusCode
    ]);
}
```

## Next Steps

1. **Database Integration**: Create models and migrations to store product data
2. **Sync Command**: Create scheduled job to sync products periodically
3. **Webhooks**: Implement webhooks if available for real-time updates
4. **Caching**: Implement Redis/Memcached for product data
5. **Queue Jobs**: Use Laravel queues for bulk imports
6. **API Rate Limiting**: Implement rate limiting in your application

## Support

- API Documentation: https://api.psrestful.com/docs
- PromoStandards: https://promostandards.org

## License

This integration is proprietary to your application.
