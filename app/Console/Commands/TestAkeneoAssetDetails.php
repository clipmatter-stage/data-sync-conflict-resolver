<?php

namespace App\Console\Commands;

use App\Services\AkeneoService;
use Illuminate\Console\Command;

class TestAkeneoAssetDetails extends Command
{
    protected $signature = 'akeneo:asset-details';
    protected $description = 'Get detailed asset information';

    public function handle(AkeneoService $akeneoService): int
    {
        $this->info('Fetching asset details from imported_assets family...');
        $this->newLine();

        // Get product with assets
        $result = $akeneoService->getProducts(['limit' => 1]);
        
        if (!$result['success'] || empty($result['data']['results'])) {
            $this->error('Failed to fetch products');
            return self::FAILURE;
        }

        $product = $result['data']['results'][0];
        $values = $product['values'] ?? [];
        $assetData = $values['imported_assets'][0]['data'] ?? null;
        
        if (!is_array($assetData) || empty($assetData)) {
            $this->warn('No assets found');
            return self::FAILURE;
        }

        $assetCode = $assetData[0];
        $this->info("Asset Code: {$assetCode}");
        $this->newLine();

        // Fetch asset details
        $reflection = new \ReflectionClass($akeneoService);
        $method = $reflection->getMethod('makeRequest');
        $method->setAccessible(true);
        
        $result = $method->invoke($akeneoService, 'GET', "/api/rest/v1/asset-families/imported_assets/assets/{$assetCode}");
        
        if ($result['success']) {
            $asset = $result['data'];
            
            $this->info('Asset Details:');
            $this->line(json_encode($asset, JSON_PRETTY_PRINT));
            $this->newLine();
            
            // Show values structure
            $values = $asset['values'] ?? [];
            $this->info('Available attributes in asset:');
            foreach ($values as $attr => $data) {
                $this->line("  - {$attr}");
            }
        } else {
            $this->error('Failed to fetch asset: ' . ($result['error'] ?? 'Unknown'));
        }

        return self::SUCCESS;
    }
}
