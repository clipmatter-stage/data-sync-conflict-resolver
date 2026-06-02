<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class RefreshShopifyToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopify:refresh-token {shop_id=2}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh Shopify access token using refresh token';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $shopId = $this->argument('shop_id');
        
        $this->info("🔄 Refreshing Shopify Access Token for Shop ID: {$shopId}");
        $this->newLine();
        
        // Load shop
        $shop = User::find($shopId);
        
        if (!$shop) {
            $this->error("❌ Shop not found!");
            return 1;
        }
        
        $this->info("✅ Shop: {$shop->name}");
        
        // Check if refresh token exists
        if (empty($shop->shopify_offline_refresh_token)) {
            $this->error("❌ No refresh token found!");
            $this->warn("Please re-authenticate with Shopify.");
            return 1;
        }
        
        try {
            // Decrypt refresh token
            $refreshToken = Crypt::decryptString($shop->shopify_offline_refresh_token);
            
            $this->info("📡 Requesting new access token from Shopify...");
            
            // Make token refresh request
            $response = Http::asForm()->post("https://{$shop->name}/admin/oauth/access_token", [
                'client_id' => config('shopify-app.api_key'),
                'client_secret' => config('shopify-app.api_secret'),
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
            ]);
            
            if ($response->failed()) {
                $this->error("❌ Token refresh failed!");
                $this->line("Status: " . $response->status());
                $this->line("Response: " . $response->body());
                return 1;
            }
            
            $data = $response->json();
            
            if (!isset($data['access_token'])) {
                $this->error("❌ No access token in response!");
                $this->line(json_encode($data, JSON_PRETTY_PRINT));
                return 1;
            }
            
            // Update shop with new tokens
            DB::table('users')->where('id', $shop->id)->update([
                'shopify_offline_access_token' => Crypt::encryptString($data['access_token']),
                'shopify_offline_access_token_expires_at' => now()->addSeconds($data['expires_in']),
            ]);
            
            // Update refresh token if provided
            if (isset($data['refresh_token'])) {
                DB::table('users')->where('id', $shop->id)->update([
                    'shopify_offline_refresh_token' => Crypt::encryptString($data['refresh_token']),
                ]);
            }
            
            // Reload shop
            $shop->refresh();
            
            $this->newLine();
            $this->info("✅ Token refreshed successfully!");
            $this->table(
                ['Property', 'Value'],
                [
                    ['Access Token', 'Set (encrypted)'],
                    ['Expires At', $shop->shopify_offline_access_token_expires_at],
                    ['Expires In', $data['expires_in'] . ' seconds'],
                ]
            );
            
            $this->newLine();
            $this->info("🧪 Testing API connection...");
            $this->call('shopify:test-auth', ['shop_id' => $shopId]);
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error("❌ Exception: " . $e->getMessage());
            $this->line($e->getTraceAsString());
            return 1;
        }
    }
}
