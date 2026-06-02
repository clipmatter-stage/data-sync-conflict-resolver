# Akeneo PIM Integration Guide

This application has been configured to use **Akeneo PIM** as the product data source instead of PS RESTful.

## 🔧 Configuration

### 1. Update Environment Variables

Edit your `.env` file and configure the following Akeneo credentials:

```env
# Akeneo PIM Configuration
AKENEO_API_URL=https://prov-tagr6gq7js.trial.akeneo.cloud
AKENEO_CLIENT_ID=2_3v2tgyl0b2o0wsc0g0ko80w8w8k8ks8g4wo4ocwogw4404ccg4
AKENEO_CLIENT_SECRET=1p36yss637esgsosoww844wskcwkgo8000kkwgs4kwwsocwgkg
AKENEO_USERNAME=your_api_username
AKENEO_PASSWORD=your_api_password
AKENEO_TIMEOUT=30
AKENEO_PAGE_SIZE=100
AKENEO_USE_UUID=true
```

### 2. How to Get Akeneo Credentials

1. Log into your Akeneo PIM instance
2. Navigate to `Connect > Connection settings`
3. Click `Create` to create a new connection
4. Enter a label (e.g., "Shopify Sync")
5. Select a flow type
6. Click `Save`

You will see:
- **Client ID** and **Client Secret** in the "Credentials" section
- **Username** and **Password** (auto-generated with connection)

**Important:** Save the password immediately as Akeneo only shows it once!

## 🧪 Testing the Connection

Run this command to test your Akeneo connection:

```bash
php artisan akeneo:test-connection
```

This will:
- Authenticate with Akeneo using OAuth2
- Fetch and display sample products
- Verify your credentials are working

## 📡 API Endpoints

### Test Connection
```
GET /api/akeneo/test-connection
```

### Get Products
```
GET /api/akeneo/products?limit=100&search=shirt
```

### Get Single Product
```
GET /api/akeneo/products/{identifier}
```
- `identifier` can be SKU or UUID

## 🔄 How It Works

### Authentication Flow

1. **OAuth2 Token Generation**
   - Encodes `client_id:client_secret` in Base64
   - Sends POST request to `/api/oauth/v1/token`
   - Receives `access_token` (valid for 1 hour)
   - Token is cached automatically

2. **API Requests**
   - All requests include `Authorization: Bearer {access_token}`
   - Tokens auto-refresh when expired

### Product Sync Integration

The `AkeneoService` replaces `PSRestfulService` and provides:

- **getProducts()** - Fetch all products with pagination
- **getProductByIdentifier()**  - Get product by SKU
- **getProductByUuid()** - Get product by UUID (recommended)
- **createProduct()** - Create new product
- **updateProduct()** - Update existing product

### Data Mapping

Akeneo products are automatically mapped to Shopify-compatible format:

| Akeneo Field | Shopify Field |
|--------------|---------------|
| identifier | SKU |
| values.name | Title |
| values.description | Description |
| values.price | Price |
| enabled | Status (active/draft) |
| categories | Tags |
| family | Product Type |
| values.image | Images |

## 📚 Product Structure

### Akeneo Product Format
```json
{
  "identifier": "product-sku",
  "uuid": "12345678-1234-1234-1234-123456789012",
  "enabled": true,
  "family": "clothing",
  "categories": ["summer", "tshirts"],
  "values": {
    "name": [
      {"locale": "en_US", "scope": null, "data": "Summer T-Shirt"}
    ],
    "description": [
      {"locale": "en_US", "scope": null, "data": "Comfortable cotton t-shirt"}
    ],
    "price": [
      {"locale": null, "scope": null, "data": [
        {"amount": "29.99", "currency": "USD"}
      ]}
    ]
  }
}
```

## 🔍 Troubleshooting

### Authentication Errors

**Error:** `Failed to obtain access token`

✅ **Solutions:**
1. Verify `AKENEO_CLIENT_ID` and `AKENEO_CLIENT_SECRET` are correct
2. Check Base64 encoding is working
3. Ensure `AKENEO_API_URL` is correct (no trailing slash)
4. Verify your connection is active in Akeneo admin

### Product Fetch Errors

**Error:** `401 Unauthorized`

✅ **Solutions:**
1. Check `AKENEO_USERNAME` and `AKENEO_PASSWORD`
2. Verify API user has proper permissions
3. Clear cached token: `php artisan cache:clear`

### Missing Products

**Error:** `No products found`

✅ **Solutions:**
1. Verify products exist in Akeneo PIM
2. Check product filters and scopes
3. Ensure products are `enabled: true`
4. Use `akeneo:test-connection` to see sample products

## 📖 Akeneo API Documentation

- [API Reference](https://api.akeneo.com/api-reference-index.html)
- [Authentication Guide](https://api.akeneo.com/documentation/authentication.html)
- [Products API](https://api.akeneo.com/api-reference.html#get_products)

## 🎯 Next Steps

1. **Configure your .env** with proper Akeneo credentials
2. **Test the connection** using `php artisan akeneo:test-connection`
3. **Run product sync** - the sync will now use Akeneo instead of PS RESTful
4. **Monitor logs** in `storage/logs/laravel.log` for any issues

## ⚙️ Advanced Configuration

### Using UUID Endpoint (Recommended)

Set `AKENEO_USE_UUID=true` in .env to use UUID-based endpoints. UUIDs are guaranteed to be unique and never change, even if SKUs are modified.

### Pagination

Default page size is 100 products. Adjust with:
```env
AKENEO_PAGE_SIZE=50
```

### Timeouts

Default timeout is 30 seconds. Increase for slow connections:
```env
AKENEO_TIMEOUT=60
```

---

**Need Help?** Check the [Akeneo API Documentation](https://api.akeneo.com) or review logs in `storage/logs/laravel.log`
