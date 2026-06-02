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
        Schema::create('product_sync_conflicts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id')->nullable()->index();
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->string('ps_product_id')->nullable();
            $table->string('shopify_product_id')->nullable();
            $table->string('field_name')->index();
            $table->text('ps_value')->nullable();
            $table->text('shopify_value')->nullable();
            $table->text('resolved_value')->nullable();
            $table->enum('status', ['pending', 'resolved', 'ignored', 'failed'])->default('pending')->index();
            $table->enum('resolution_source', ['ps_restful', 'shopify', 'custom', 'ignored'])->nullable();
            $table->timestamp('detected_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolved_by')->nullable();
            $table->timestamps();

            // Composite indexes
            $table->index(['shop_id', 'status']);
            $table->index(['shop_id', 'field_name']);
            $table->index(['product_id', 'field_name', 'status']);
            
            // Unique constraint to prevent duplicate pending conflicts
            $table->unique(['product_id', 'field_name', 'status'], 'unique_pending_conflict');
            
            // Foreign key
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_sync_conflicts');
    }
};
