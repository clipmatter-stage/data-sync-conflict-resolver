<?php

namespace App\Console\Commands;

use App\Models\ProductSyncConflict;
use Illuminate\Console\Command;

class CheckConflicts extends Command
{
    protected $signature = 'check:conflicts';
    protected $description = 'Check product sync conflicts status';

    public function handle(): int
    {
        $total = ProductSyncConflict::count();
        $pending = ProductSyncConflict::where('status', 'pending')->count();
        $resolved = ProductSyncConflict::where('status', 'resolved')->count();
        
        $this->info("Product Sync Conflicts Summary");
        $this->newLine();
        
        $this->table(
            ['Status', 'Count'],
            [
                ['Total Conflicts', $total],
                ['Pending', $pending],
                ['Resolved', $resolved],
            ]
        );
        
        if ($pending > 0) {
            $this->newLine();
            $this->info('Recent Pending Conflicts:');
            
            $conflicts = ProductSyncConflict::where('status', 'pending')
                ->with('product')
                ->latest()
                ->limit(5)
                ->get();
            
            foreach ($conflicts as $conflict) {
                $this->line("  ID: {$conflict->id}");
                $this->line("    Product: {$conflict->product->title}");
                $this->line("    Field: {$conflict->conflict_field}");
                $this->line("    Source Value: {$conflict->source_value}");
                $this->line("    Shopify Value: {$conflict->shopify_value}");
                $this->newLine();
            }
        }

        return self::SUCCESS;
    }
}
