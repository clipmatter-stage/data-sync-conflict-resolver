<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

class CheckProductImages extends Command
{
    protected $signature = 'check:images {product_id?}';
    protected $description = 'Check if products have images';

    public function handle(): int
    {
        $productId = $this->argument('product_id');
        
        if ($productId) {
            $product = Product::find($productId);
            if (!$product) {
                $this->error("Product {$productId} not found");
                return self::FAILURE;
            }
            $products = collect([$product]);
        } else {
            $products = Product::latest()->limit(5)->get();
        }

        foreach ($products as $product) {
            $this->info("Product #{$product->id}: {$product->title}");
            $this->line("  SKU: {$product->sku}");
            
            $imageUrls = is_string($product->image_urls) 
                ? json_decode($product->image_urls, true) 
                : $product->image_urls;
            
            if (empty($imageUrls)) {
                $this->warn("  ❌ No images stored");
            } else {
                $this->info("  ✓ " . count($imageUrls) . " image(s) stored:");
                foreach ($imageUrls as $idx => $url) {
                    $this->line("    [{$idx}] {$url}");
                }
            }
            
            // Check raw payload
            $rawPayload = is_string($product->raw_ps_payload) 
                ? json_decode($product->raw_ps_payload, true) 
                : $product->raw_ps_payload;
            
            if ($rawPayload && isset($rawPayload['values']['imported_assets'])) {
                $assets = $rawPayload['values']['imported_assets'];
                $this->line("  Akeneo imported_assets: " . json_encode($assets));
            }
            
            $this->newLine();
        }

        return self::SUCCESS;
    }
}
