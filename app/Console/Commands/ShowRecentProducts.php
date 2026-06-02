<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

class ShowRecentProducts extends Command
{
    protected $signature = 'products:recent {count=10}';
    protected $description = 'Show most recently created products';

    public function handle(): int
    {
        $count = (int) $this->argument('count');
        $products = Product::latest('created_at')->limit($count)->get();

        if ($products->isEmpty()) {
            $this->warn('No products found');
            return self::SUCCESS;
        }

        $this->info("Showing {$products->count()} most recent products:");
        $this->newLine();

        $tableData = [];
        foreach ($products as $product) {
            $tableData[] = [
                $product->id,
                substr($product->ps_product_id ?? '', 0, 20) . '...',
                $product->title,
                $product->sku,
                $product->vendor ?? 'N/A',
                $product->created_at->format('Y-m-d H:i:s'),
                $product->shopify_product_id ? '✓' : '✗',
            ];
        }

        $this->table(
            ['ID', 'UUID', 'Title', 'SKU', 'Vendor', 'Created', 'In Shopify'],
            $tableData
        );

        return self::SUCCESS;
    }
}
