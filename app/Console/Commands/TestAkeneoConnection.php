<?php

namespace App\Console\Commands;

use App\Services\AkeneoService;
use Illuminate\Console\Command;

class TestAkeneoConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'akeneo:test-connection';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test connection to Akeneo PIM API';

    /**
     * Execute the console command.
     */
    public function handle(AkeneoService $akeneoService): int
    {
        $this->info('Testing Akeneo PIM API connection...');
        $this->newLine();

        // Test connection
        $result = $akeneoService->testConnection();

        if ($result['success']) {
            $this->info('✅ Successfully connected to Akeneo PIM!');
            $this->newLine();
            
            $this->info('API Information:');
            $this->line('  API URL: ' . config('akeneo.api_url'));
            $this->line('  Client ID: ' . config('akeneo.client_id'));
            $this->line('  Username: ' . config('akeneo.username'));
            $this->newLine();

            // Get sample products
            $this->info('Fetching sample products...');
            $productsResult = $akeneoService->getProducts(['limit' => 5]);

            if ($productsResult['success']) {
                $products = $productsResult['data']['results'] ?? [];
                $totalCount = $productsResult['data']['count'] ?? 0;

                $this->info("✅ Found {$totalCount} products in total");
                $this->newLine();

                if (count($products) > 0) {
                    $this->info('Sample products:');
                    foreach (array_slice($products, 0, 5) as $index => $product) {
                        $identifier = $product['identifier'] ?? $product['uuid'] ?? 'N/A';
                        $enabled = ($product['enabled'] ?? false) ? '✓' : '✗';
                        $family = $product['family'] ?? 'N/A';
                        
                        $this->line(sprintf(
                            '  %d. %s [%s] - Family: %s - Enabled: %s',
                            $index + 1,
                            $identifier,
                            $product['uuid'] ?? 'no UUID',
                            $family,
                            $enabled
                        ));
                    }
                }
            } else {
                $this->warn('⚠️  Could not fetch products: ' . ($productsResult['error'] ?? 'Unknown error'));
            }

            return self::SUCCESS;
        }

        $this->error('❌ Failed to connect to Akeneo PIM');
        $this->error('Error: ' . ($result['error'] ?? 'Unknown error'));
        $this->newLine();
        
        $this->warn('Please check your configuration in .env:');
        $this->line('  AKENEO_API_URL');
        $this->line('  AKENEO_CLIENT_ID');
        $this->line('  AKENEO_CLIENT_SECRET');
        $this->line('  AKENEO_USERNAME');
        $this->line('  AKENEO_PASSWORD');

        return self::FAILURE;
    }
}
