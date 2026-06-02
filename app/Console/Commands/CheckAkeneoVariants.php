<?php

namespace App\Console\Commands;

use App\Services\AkeneoService;
use Illuminate\Console\Command;

class CheckAkeneoVariants extends Command
{
    protected $signature = 'akeneo:check-variants';
    protected $description = 'Check if Akeneo has product models and variants';

    public function handle(AkeneoService $akeneo): int
    {
        $this->info('Checking Akeneo for Product Models and Variants...');
        $this->newLine();

        // Check for product models
        $this->line('🔍 Searching for Product Models...');
        
        $result = $akeneo->getProducts(['limit' => 100]);
        
        if (!$result['success']) {
            $this->error('Failed to fetch products');
            return self::FAILURE;
        }

        $products = $result['data']['results'] ?? [];
        
        $productModels = 0;
        $variantProducts = 0;
        $simpleProducts = 0;
        $exampleVariants = [];
        
        foreach ($products as $product) {
            $parent = $product['parent'] ?? null;
            $family = $product['family'] ?? null;
            $familyVariant = $product['family_variant'] ?? null;
            
            // Check if it's a product model (has family_variant, no parent)
            if ($familyVariant && !$parent) {
                $productModels++;
            }
            // Check if it's a variant product (has parent)
            elseif ($parent) {
                $variantProducts++;
                
                if (count($exampleVariants) < 3) {
                    $exampleVariants[] = [
                        'uuid' => $product['uuid'] ?? $product['identifier'] ?? 'N/A',
                        'parent' => $parent,
                        'family_variant' => $familyVariant,
                        'values' => array_keys($product['values'] ?? [])
                    ];
                }
            }
            // Simple product (no parent, no family_variant or just family)
            else {
                $simpleProducts++;
            }
        }

        $this->newLine();
        $this->info('📊 RESULTS:');
        $this->table(
            ['Type', 'Count', 'Description'],
            [
                ['Product Models', $productModels, 'Parent containers for variants'],
                ['Variant Products', $variantProducts, 'Child products with parent link'],
                ['Simple Products', $simpleProducts, 'Standalone products (no variants)']
            ]
        );

        if ($variantProducts > 0) {
            $this->newLine();
            $this->info('✅ Your Akeneo HAS product variants!');
            $this->warn('⚠️  But your current sync treats them as separate simple products.');
            $this->newLine();
            
            $this->info('Example Variant Products:');
            foreach ($exampleVariants as $i => $variant) {
                $num = $i + 1;
                $this->line("#$num:");
                $this->line("  UUID: {$variant['uuid']}");
                $this->line("  Parent: {$variant['parent']}");
                $this->line("  Family Variant: {$variant['family_variant']}");
                $this->line("  Attributes: " . implode(', ', array_slice($variant['values'], 0, 5)));
                $this->newLine();
            }
            
            $this->newLine();
            $this->warn('🚨 ACTION REQUIRED:');
            $this->line('You need to implement variant support to properly sync these products.');
            $this->line('See OPTIMIZATION_PLAN.md for detailed implementation guide.');
        } else {
            $this->info('ℹ️  No product variants found in this batch.');
            $this->line('Your products are simple products, current sync is appropriate.');
        }

        return self::SUCCESS;
    }
}
