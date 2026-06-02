<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id')->nullable()->index();
            $table->string('ps_product_id')->nullable()->index();
            $table->string('shopify_product_id')->nullable()->index();
            $table->string('shopify_variant_id')->nullable();
            $table->string('title')->nullable();
            $table->string('sku')->nullable()->index();
            $table->text('description')->nullable();
            $table->string('vendor')->nullable();
            $table->string('product_type')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('compare_at_price', 10, 2)->nullable();
            $table->integer('inventory_quantity')->default(0);
            $table->string('status')->default('active');
            $table->json('tags')->nullable();
            $table->json('image_urls')->nullable();
            $table->json('raw_ps_payload')->nullable();
            $table->json('raw_shopify_payload')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            // Composite indexes
            $table->index(['shop_id', 'ps_product_id']);
            $table->index(['shop_id', 'shopify_product_id']);
            $table->index(['shop_id', 'sku']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
