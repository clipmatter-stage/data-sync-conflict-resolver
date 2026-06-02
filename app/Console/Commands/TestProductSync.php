<?php

namespace App\Console\Commands;

use App\Services\ProductSync\ProductSyncService;
use Illuminate\Console\Command;

class TestProductSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:product-sync {shop_id=2}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test product sync from Akeneo to Shopify';

    /**
     * Execute the console command.
     */
    public function handle(ProductSyncService $syncService): int
    {
        $shopId = $this->argument('shop_id');

        $this->info("Testing product sync for shop ID: {$shopId}");
        $this->newLine();

        try {
            $result = $syncService->syncProducts($shopId, []);

            if ($result['success']) {
                $this->info('✅ Product sync completed successfully!');
                $this->newLine();
                
                $summary = $result['summary'];
                $this->table(
                    ['Metric', 'Value'],
                    [
                        ['Total Akeneo Products', $summary['total_ps_products']],
                        ['Created in Shopify', $summary['created_in_shopify']],
                        ['Updated Local Products', $summary['updated_local_products']],
                        ['Conflicts Detected', $summary['conflicts_detected']],
                        ['Conflicts Updated', $summary['conflicts_updated']],
                        ['Failed Products', $summary['failed_products']],
                    ]
                );

                return self::SUCCESS;
            }

            $this->error('❌ Product sync failed');
            $this->error('Error: ' . ($result['error'] ?? 'Unknown error'));

            return self::FAILURE;

        } catch (\Exception $e) {
            $this->error('❌ Exception during sync');
            $this->error($e->getMessage());
            $this->newLine();
            $this->line($e->getTraceAsString());

            return self::FAILURE;
        }
    }
}
