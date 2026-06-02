<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TestShopifyAuth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopify:test-auth {shop_id=2}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Shopify authentication and API access';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $shopId = $this->argument('shop_id');
        
        $this->info("🔍 Testing Shopify Authentication for Shop ID: {$shopId}");
        $this->newLine();
        
        // Load shop
        $shop = User::find($shopId);
        
        if (!$shop) {
            $this->error("❌ Shop not found!");
            return 1;
        }
        
        $this->info("✅ Shop found: {$shop->name}");
        $this->newLine();
        
        // Check database columns
        $this->info("📊 Database Token Status:");
        
        $shopData = DB::table('users')->where('id', $shopId)->first();
        
        $this->table(
            ['Field', 'Status', 'Value'],
            [
                [
                    'shopify_offline_access_token',
                    !empty($shopData->shopify_offline_access_token) ? '✅ EXISTS' : '❌ MISSING',
                    !empty($shopData->shopify_offline_access_token) 
                        ? substr($shopData->shopify_offline_access_token, 0, 50) . '...' 
                        : 'NULL'
                ],
                [
                    'shopify_offline_refresh_token',
                    !empty($shopData->shopify_offline_refresh_token) ? '✅ EXISTS' : '❌ MISSING',
                    !empty($shopData->shopify_offline_refresh_token) ? 'Encrypted (length: ' . strlen($shopData->shopify_offline_refresh_token) . ')' : 'NULL'
                ],
                [
                    'shopify_offline_access_token_expires_at',
                    !empty($shopData->shopify_offline_access_token_expires_at) ? '✅ SET' : '❌ MISSING',
                    $shopData->shopify_offline_access_token_expires_at ?? 'NULL'
                ],
            ]
        );
        
        $this->newLine();
        
        // Test API call
        if (!empty($shopData->shopify_offline_access_token)) {
            $this->info("🧪 Testing Shopify GraphQL API...");
            
            try {
                $query = <<<'GRAPHQL'
                {
                    shop {
                        name
                        email
                        plan {
                            displayName
                        }
                    }
                }
                GRAPHQL;
                
                $response = $shop->api()->graph($query);
                
                if (isset($response['errors'])) {
                    $this->error("❌ GraphQL API Error:");
                    $this->line(json_encode($response['errors'], JSON_PRETTY_PRINT));
                    
                    if (isset($response['body'])) {
                        $this->newLine();
                        $this->warn("Response Body: " . $response['body']);
                    }
                    
                    return 1;
                }
                
                if (isset($response['body']['data']['shop'])) {
                    $shopData = $response['body']['data']['shop'];
                    $this->info("✅ API Connection Successful!");
                    $this->newLine();
                    $this->table(
                        ['Property', 'Value'],
                        [
                            ['Shop Name', $shopData['name'] ?? 'N/A'],
                            ['Email', $shopData['email'] ?? 'N/A'],
                            ['Plan', $shopData['plan']['displayName'] ?? 'N/A'],
                        ]
                    );
                    
                    return 0;
                }
                
                $this->warn("⚠️  Unexpected API response format");
                $this->line(json_encode($response, JSON_PRETTY_PRINT));
                
            } catch (\Exception $e) {
                $this->error("❌ Exception: " . $e->getMessage());
                $this->line("Trace: " . $e->getTraceAsString());
                return 1;
            }
        } else {
            $this->error("❌ Access token is missing!");
            $this->newLine();
            $this->warn("⚠️  You need to re-authenticate with Shopify:");
            $this->line("1. Visit: https://{$shop->name}/admin/apps");
            $this->line("2. Uninstall and reinstall the app, OR");
            $this->line("3. Visit: http://localhost/authenticate?shop={$shop->name}");
            $this->newLine();
            
            return 1;
        }
        
        return 0;
    }
}
