<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ProductSync\ProductSyncService;
use Illuminate\Console\Command;
use App\Models\User;

class TestSingleProductSync extends Command
{
    protected $signature = 'test:single-product {shop_id=2} {--fresh : Delete existing products first}';
    protected $description = 'Test syncing a single product with detailed logs';

    public function handle(ProductSyncService $syncService): int
    {
        $shopId = $this->argument('shop_id');
        $fresh = $this->option('fresh');
        
        $shop = User::find($shopId);
        
        if (!$shop) {
            $this->error("Shop with ID {$shopId} not found");
            return self::FAILURE;
        }

        $this->info("Testing product sync for shop: {$shop->name}");
        $this->newLine();

        if ($fresh) {
            $this->warn('Deleting existing products...');
            $deleted = Product::where('shop_id', $shopId)->delete();
            $this->info("Deleted {$deleted} products");
            $this->newLine();
        }

        $this->info('Syncing 1 product from Akeneo...');
        $this->newLine();

        $result = $syncService->syncProducts($shopId, ['limit' => 1]);

        if (!$result['success']) {
            $this->error('Sync failed: ' . ($result['error'] ?? 'Unknown error'));
            return self::FAILURE;
        }

        $summary = $result['summary'] ?? [];

        $this->newLine();
        $this->info('=== SYNC RESULT ===');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Akeneo Products', $summary['total_ps_products'] ?? 0],
                ['Created in Shopify', $summary['created_in_shopify'] ?? 0],
                ['Updated Local Products', $summary['updated_local_products'] ?? 0],
                ['Conflicts Detected', $summary['conflicts_detected'] ?? 0],
                ['Conflicts Updated', $summary['conflicts_updated'] ?? 0],
                ['Failed Products', $summary['failed_products'] ?? 0],
            ]
        );

        $this->newLine();
        $this->info('✅ Check storage/logs/laravel.log for detailed product creation logs');
        $this->info('   Look for: "Akeneo Product Mapping", "Normalized Product for Sync", "Shopify productCreate"');
        
        return self::SUCCESS;
    }
}
