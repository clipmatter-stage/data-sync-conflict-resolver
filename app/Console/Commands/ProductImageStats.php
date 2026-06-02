<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

class ProductImageStats extends Command
{
    protected $signature = 'stats:images';
    protected $description = 'Show image statistics for products';

    public function handle(): int
    {
        $total = Product::count();
        $withImages = Product::whereNotNull('image_urls')
            ->where('image_urls', '!=', '[]')
            ->where('image_urls', '!=', 'null')
            ->count();
        
        $this->info('Product Image Statistics');
        $this->newLine();
        
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Products', $total],
                ['Products with Images', $withImages],
                ['Products without Images', $total - $withImages],
                ['% with Images', $total > 0 ? round(($withImages / $total) * 100, 2) . '%' : '0%'],
            ]
        );
        
        $this->newLine();
        
        // Sample products with images
        $samplesWithImages = Product::whereNotNull('image_urls')
            ->where('image_urls', '!=', '[]')
            ->latest()
            ->limit(5)
            ->get(['id', 'title', 'image_urls']);
        
        if ($samplesWithImages->isNotEmpty()) {
            $this->info('Recent products WITH images:');
            foreach ($samplesWithImages as $product) {
                $imageUrls = is_string($product->image_urls) 
                    ? json_decode($product->image_urls, true) 
                    : $product->image_urls;
                $imageCount = is_array($imageUrls) ? count($imageUrls) : 0;
                $this->line("  #{$product->id}: {$product->title} ({$imageCount} images)");
            }
        }

        return self::SUCCESS;
    }
}
