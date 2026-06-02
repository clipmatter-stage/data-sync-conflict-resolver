<?php

namespace App\Console\Commands;

use App\Services\AkeneoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class LogAkeneoData extends Command
{
    protected $signature = 'akeneo:log-data {count=3 : Number of products to log}';
    protected $description = 'Log raw Akeneo product data to see structure';

    public function handle(AkeneoService $akeneoService): int
    {
        $count = (int) $this->argument('count');
        
        $this->info("Fetching {$count} products from Akeneo...");
        $this->newLine();

        $result = $akeneoService->getProducts(['limit' => $count]);

        if (!$result['success']) {
            $this->error('Failed to fetch products from Akeneo');
            $this->error($result['error'] ?? 'Unknown error');
            return self::FAILURE;
        }

        $products = $result['data']['results'] ?? [];
        $this->info("Retrieved " . count($products) . " products");
        $this->newLine();

        foreach ($products as $index => $product) {
            $this->line("=== PRODUCT " . ($index + 1) . " ===");
            $this->newLine();
            
            // Log the full product structure
            Log::info("Akeneo Product #{$index}", [
                'raw_product' => $product,
            ]);

            // Display key information
            $this->table(
                ['Field', 'Value'],
                [
                    ['uuid', $product['uuid'] ?? 'N/A'],
                    ['identifier', $product['identifier'] ?? 'N/A'],
                    ['enabled', $product['enabled'] ? 'true' : 'false'],
                    ['family', $product['family'] ?? 'N/A'],
                    ['categories', json_encode($product['categories'] ?? [])],
                ]
            );

            $this->newLine();
            $this->line("VALUES STRUCTURE:");
            $this->newLine();

            $values = $product['values'] ?? [];
            
            if (empty($values)) {
                $this->warn("  No values found in product!");
            } else {
                foreach ($values as $attributeCode => $attributeValues) {
                    $this->line("  Attribute: {$attributeCode}");
                    
                    foreach ($attributeValues as $valueIndex => $value) {
                        $locale = $value['locale'] ?? 'no_locale';
                        $scope = $value['scope'] ?? 'no_scope';
                        $data = $value['data'] ?? 'null';
                        
                        if (is_array($data)) {
                            $data = json_encode($data);
                        }
                        
                        $this->line("    [{$valueIndex}] locale={$locale}, scope={$scope}, data={$data}");
                    }
                    $this->newLine();
                }
            }

            // Display expanded mapping
            $this->newLine();
            $this->line("EXPANDED MAPPING:");
            $expanded = $akeneoService->getProductsExpanded(['limit' => 1, 'search' => json_encode(['uuid' => [['operator' => '=', 'value' => $product['uuid']]]])]);
            
            if ($expanded['success'] && !empty($expanded['data']['results'])) {
                $mapped = $expanded['data']['results'][0];
                $this->table(
                    ['Mapped Field', 'Value'],
                    [
                        ['productId', $mapped['productId'] ?? 'N/A'],
                        ['name', $mapped['name'] ?? 'N/A'],
                        ['sku', $mapped['sku'] ?? 'N/A'],
                        ['description', substr($mapped['description'] ?? 'N/A', 0, 50)],
                        ['brandName', $mapped['brandName'] ?? 'N/A'],
                        ['listPrice', $mapped['listPrice'] ?? 'N/A'],
                        ['primaryImageUrl', $mapped['primaryImageUrl'] ?? 'N/A'],
                    ]
                );
            }

            $this->newLine();
            $this->line(str_repeat('=', 80));
            $this->newLine();
        }

        $this->info("✅ Check storage/logs/laravel.log for full raw JSON data");
        
        return self::SUCCESS;
    }
}
