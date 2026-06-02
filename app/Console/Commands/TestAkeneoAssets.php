<?php

namespace App\Console\Commands;

use App\Services\AkeneoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TestAkeneoAssets extends Command
{
    protected $signature = 'akeneo:test-assets';
    protected $description = 'Test Akeneo asset endpoints';

    public function handle(AkeneoService $akeneoService): int
    {
        $this->info('Testing Akeneo Asset APIs...');
        $this->newLine();

        // Get a product with assets
        $result = $akeneoService->getProducts(['limit' => 1]);
        
        if (!$result['success'] || empty($result['data']['results'])) {
            $this->error('Failed to fetch products');
            return self::FAILURE;
        }

        $product = $result['data']['results'][0];
        $values = $product['values'] ?? [];
        
        $this->info("Product: " . ($product['uuid'] ?? 'N/A'));
        $this->newLine();

        if (!isset($values['imported_assets'])) {
            $this->warn('No imported_assets found');
            return self::FAILURE;
        }

        $assetData = $values['imported_assets'][0]['data'] ?? null;
        
        if (!is_array($assetData) || empty($assetData)) {
            $this->warn('No asset codes found');
            return self::FAILURE;
        }

        $assetCode = $assetData[0];
        $this->info("Testing asset code: {$assetCode}");
        $this->newLine();

        // Test different endpoint patterns
        $endpoints = [
            'Asset Manager' => "/api/rest/v1/asset-manager/assets/{$assetCode}",
            'PAM Assets' => "/api/rest/v1/pam/assets/{$assetCode}",
            'Assets' => "/api/rest/v1/assets/{$assetCode}",
            'Asset Families' => "/api/rest/v1/asset-families",
        ];

        foreach ($endpoints as $name => $endpoint) {
            $this->line("Testing: {$name}");
            $this->line("  Endpoint: {$endpoint}");
            
            $reflection = new \ReflectionClass($akeneoService);
            $method = $reflection->getMethod('makeRequest');
            $method->setAccessible(true);
            
            $result = $method->invoke($akeneoService, 'GET', $endpoint);
            
            if ($result['success']) {
                $this->info("  ✓ SUCCESS!");
                $this->line("  Response: " . json_encode($result['data'], JSON_PRETTY_PRINT));
            } else {
                $this->error("  ✗ Failed: " . ($result['error'] ?? 'Unknown error'));
            }
            $this->newLine();
        }

        // Test if we can access media files directly
        $this->info('Testing direct media file approach...');
        $this->line("Since Asset Manager returns 404, the assets might be:");
        $this->line("1. In a different Akeneo module (PAM, not Asset Manager)");
        $this->line("2. Media files that need different endpoint");
        $this->line("3. Not accessible in trial instances");
        $this->newLine();
        
        $this->warn('💡 Recommendation: For now, products will sync WITHOUT images.');
        $this->warn('   Contact Akeneo support to confirm available asset APIs in your trial instance.');

        return self::SUCCESS;
    }
}
