<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

class CheckProductStatus extends Command
{
    protected $signature = 'check:products';
    protected $description = 'Check product sync status';

    public function handle(): int
    {
        $this->info('Product Database Status');
        $this->newLine();

        $total = Product::count();
        $withShopify = Product::whereNotNull('shopify_product_id')->count();
        $recent = Product::orderBy('updated_at', 'desc')->limit(5)->get();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Products in DB', $total],
                ['Products with Shopify ID', $withShopify],
                ['Products without Shopify ID', $total - $withShopify],
            ]
        );

        $this->newLine();
        $this->info('5 Most Recently Updated Products:');
        $this->newLine();

        foreach ($recent as $product) {
            $uuid = substr($product->ps_product_id, 0, 36);
            $this->line("ID: {$product->id}");
            $this->line("  UUID: {$uuid}");
            $this->line("  Title: {$product->title}");
            $this->line("  Shopify ID: " . ($product->shopify_product_id ?? 'NULL'));
            $this->line("  SKU: " . ($product->sku ?? 'NULL'));
            $this->line("  Updated: {$product->updated_at}");
            $this->newLine();
        }

        return self::SUCCESS;
    }
}
