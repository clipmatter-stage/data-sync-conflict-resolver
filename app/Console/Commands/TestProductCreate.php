<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Services\ProductSync\ShopifyProductService;

class TestProductCreate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:product-create {shop_id=2}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test creating a single product in Shopify using GraphQL';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $shopId = $this->argument('shop_id');
        
        $this->info("🧪 Testing Product Creation for Shop ID: {$shopId}");
        $this->newLine();
        
        $shop = User::find($shopId);
        
        if (!$shop) {
            $this->error("❌ Shop not found!");
            return 1;
        }
        
        $this->info("✅ Shop: {$shop->name}");
        $this->newLine();
        
        // Test product data
        $testProduct = [
            'title' => 'Test Product - ' . now()->format('Y-m-d H:i:s'),
            'description' => 'This is a test product created via GraphQL',
            'vendor' => 'HIT Promotional Products',
            'product_type' => 'Test Category',
            'status' => 'DRAFT',  // Use DRAFT so it doesn't go live
            'tags' => ['test', 'graphql'],
            'price' => '19.99',
            'sku' => 'TEST-' . time(),
            'image_urls' => [
                'https://www.hitpromo.net/imageManager/show/615_group.jpg'
            ],
        ];
        
        $this->info("📦 Creating test product...");
        $this->line("Title: {$testProduct['title']}");
        $this->line("Price: \${$testProduct['price']}");
        $this->line("SKU: {$testProduct['sku']}");
        $this->newLine();
        
        try {
            $shopifyService = new ShopifyProductService();
            $result = $shopifyService->createProduct($shop, $testProduct);
            
            if ($result) {
                $this->info("✅ Product created successfully!");
                $this->newLine();
                $this->table(
                    ['Field', 'Value'],
                    [
                        ['Shopify Product ID', $result['shopify_product_id'] ?? 'N/A'],
                        ['Title', $result['title'] ?? 'N/A'],
                        ['SKU', $result['sku'] ?? 'N/A'],
                        ['Price', '$' . ($result['price'] ?? '0')],
                        ['Status', $result['status'] ?? 'N/A'],
                    ]
                );
                
                $this->newLine();
                $this->info("🎉 Test passed! GraphQL mutations are working.");
                return 0;
            } else {
                $this->error("❌ Product creation failed!");
                $this->warn("Check the logs at storage/logs/laravel.log for details.");
                return 1;
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Exception: " . $e->getMessage());
            $this->line($e->getTraceAsString());
            return 1;
        }
    }
}
