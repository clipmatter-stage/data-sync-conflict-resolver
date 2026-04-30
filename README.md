# Data Sync Conflict Resolver - Shopify App

A Laravel + React + Shopify application built with the latest compatible versions for PHP 8.2.

## 🎯 What's Included

✅ **Laravel 12** (PHP 8.2 compatible)  
✅ **React with TypeScript**  
✅ **Inertia.js** for seamless SPA experience  
✅ **Shopify Polaris UI** components  
✅ **Shopify App Bridge** integration  
✅ **kyon147/laravel-shopify** package (v26.0.0)  
✅ **All critical bug fixes** applied from repository analysis  

## 🔧 Technology Stack

- **Backend:** Laravel 12.58.0
- **Frontend:** React + TypeScript
- **UI Framework:** Shopify Polaris
- **Database:** SQLite (default, can switch to MySQL)
- **State Management:** Inertia.js
- **Build Tool:** Vite
- **Testing:** PHPUnit + Pest

## 🐛 Critical Fixes Applied

The following critical bug fixes from the analyzed repository have been applied:

1. **Safari Cross-Origin Fix** - OAuth redirect flow works in Safari iframes
2. **Form Request Fix** - StoreUsageCharge now properly accesses request data
3. **Null Parameter Handling** - VerifyShopify middleware handles null values correctly
4. **Trial Period Protection** - Prevents trial period exploitation with hour-precision calculations

## 📁 Project Structure

```
data-sync-conflict-resolver/
├── app/                      # Laravel application code
├── config/
│   └── shopify-app.php      # Shopify configuration
├── resources/
│   ├── js/
│   │   ├── app.tsx          # Inertia entry point with Polaris
│   │   ├── pages/
│   │   │   ├── shopify-dashboard.tsx  # Main Shopify dashboard
│   │   │   ├── dashboard.tsx          # Standard dashboard
│   │   │   └── welcome.tsx            # Welcome page
│   │   ├── components/      # React components
│   │   ├── layouts/         # Page layouts
│   │   └── hooks/           # React hooks
│   ├── views/
│   │   └── app.blade.php    # Main Blade template with App Bridge
│   └── css/
│       └── app.css          # Styles
├── routes/
│   └── web.php              # Web routes with Shopify middleware
└── vendor/
    └── kyon147/laravel-shopify/  # Shopify package (with fixes)
```

## 🚀 Quick Start

### 1. Environment Configuration

Update your `.env` file with your Shopify credentials:

```env
# Shopify Configuration
SHOPIFY_API_KEY=your-api-key-here
SHOPIFY_API_SECRET=your-api-secret-here
SHOPIFY_API_SCOPES=read_products,write_products,read_orders,write_orders
SHOPIFY_BILLING_ENABLED=false
SHOPIFY_FRONTEND_ENGINE=REACT
```

### 2. Start Development Servers

**Terminal 1 - Laravel Server:**
```bash
cd c:\xampp\htdocs\data-sync-conflict-resolver
php artisan serve
```

**Terminal 2 - Vite Dev Server:**
```bash
cd c:\xampp\htdocs\data-sync-conflict-resolver
npm run dev
```

**Terminal 3 - ngrok (for HTTPS):**
```bash
ngrok http 8000
```

### 3. Configure Shopify App

1. Go to [Shopify Partners Dashboard](https://partners.shopify.com/)
2. Create a new app or use existing one
3. Configure:
   - **App URL:** `https://your-ngrok-url.ngrok-free.app`
   - **Allowed redirection URL:** `https://your-ngrok-url.ngrok-free.app/authenticate`
4. Copy API Key and Secret to your `.env` file
5. Update `APP_URL` in `.env` with your ngrok URL

### 4. Install and Test

1. Go to Shopify Partners Dashboard
2. Click "Test your app"
3. Select a development store
4. Install the app
5. You'll see the Shopify Dashboard with Polaris UI!

## 📝 Available Routes

- `/` - Shopify Dashboard (requires Shopify authentication)
- `/welcome` - Public welcome page
- `/dashboard` - Standard Laravel dashboard (requires auth)

## 🛠️ Development Commands

```bash
# Install dependencies
composer install
npm install

# Run migrations
php artisan migrate

# Generate app key
php artisan key:generate

# Clear cache
php artisan config:clear
php artisan cache:clear

# Run tests
php artisan test

# Build for production
npm run build
php artisan optimize
```

## 📦 Installed Packages

### Backend (Composer)
- `laravel/framework`: ^12.0
- `inertiajs/inertia-laravel`: ^2.0
- `kyon147/laravel-shopify`: ^26.0
- `gnikyt/basic-shopify-api`: ^11.0

### Frontend (NPM)
- `react`: ^18.3.1
- `@inertiajs/react`: Latest
- `@shopify/polaris`: Latest
- `@shopify/app-bridge-react`: Latest
- `vite`: Latest
- `typescript`: Latest

## 🔐 Environment Variables

| Variable | Description | Example |
|----------|-------------|---------|
| `SHOPIFY_API_KEY` | Your Shopify API key | `abc123def456...` |
| `SHOPIFY_API_SECRET` | Your Shopify API secret | `shpss_xyz789...` |
| `SHOPIFY_API_SCOPES` | OAuth scopes | `read_products,write_products` |
| `SHOPIFY_BILLING_ENABLED` | Enable billing | `false` |
| `SHOPIFY_FRONTEND_ENGINE` | Frontend type | `REACT` |
| `APP_URL` | Your app URL | `https://abc.ngrok-free.app` |

## 🧪 Testing

The project uses Pest for testing. Run tests with:

```bash
php artisan test
```

## 🚢 Deployment

### Option 1: Laravel Forge
1. Connect your repository
2. Set environment variables
3. Deploy

### Option 2: Heroku
```bash
heroku create your-app-name
git push heroku main
heroku config:set SHOPIFY_API_KEY=your-key
heroku config:set SHOPIFY_API_SECRET=your-secret
```

### Option 3: DigitalOcean
1. Create a droplet
2. Install LAMP/LEMP stack
3. Clone repository
4. Configure web server
5. Set up SSL with Let's Encrypt

## 📚 Additional Resources

- **Setup Guide:** `c:\xampp\htdocs\SETUP_GUIDE_FOR_PHP_8.2.md`
- **Applied Fixes:** `c:\xampp\htdocs\shopify-laravel-app\FIXES_APPLIED.md`
- **Quick Start:** `c:\xampp\htdocs\shopify-laravel-app\QUICK_START_GUIDE.md`

## 🐞 Troubleshooting

### Issue: Composer install fails
**Solution:** Temporarily disable Windows Defender/antivirus during installation

### Issue: "No host found" error
**Solution:** Access the app through Shopify Admin, not directly via URL

### Issue: Styles not loading
**Solution:** Make sure `npm run dev` is running

### Issue: Safari/cross-origin errors
**Solution:** Fixes are already applied! This should work automatically

### Issue: Trial period being exploited
**Solution:** Fix is already applied - hour-precision calculations prevent this

## 🤝 Contributing

This is a private project, but if you want to add features:

1. Create a feature branch
2. Make your changes
3. Test thoroughly
4. Submit for review

## 📄 License

Private project - All rights reserved

## 🆘 Support

For issues or questions:
- Check the documentation files in `c:\xampp\htdocs\`
- Review the FIXES_APPLIED.md for known issues
- Check Laravel documentation: https://laravel.com/docs
- Check Shopify App documentation: https://shopify.dev/docs/apps

---

**Built with** ❤️ **using Laravel, React, and Shopify Polaris**
