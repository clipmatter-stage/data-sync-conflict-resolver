<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Exclude Shopify routes from CSRF verification
        $middleware->validateCsrfTokens(except: [
            '/product-sync/*',
            '/',
            '/welcome',
            '/install',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Handle missing shop domain exception
        $exceptions->render(function (\Osiset\ShopifyApp\Exceptions\MissingShopDomainException $e, $request) {
            // Don't redirect to install if:
            // 1. Request is from iframe (Shopify admin embedded app)
            // 2. Request is for API/product-sync routes (should fail normally)
            $isIframe = $request->header('sec-fetch-dest') === 'iframe'
                || $request->hasHeader('x-requested-with');
            $isProductSyncRoute = str_starts_with($request->path(), 'product-sync/');

            if ($isIframe || $isProductSyncRoute) {
                // Let the default Shopify handler deal with it
                return null;
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Shop domain is required',
                    'message' => 'Please access this app through Shopify Admin or provide a shop parameter'
                ], 401);
            }

            // Only redirect to install for direct browser access to root
            return redirect()->route('install');
        });
    })->create();
