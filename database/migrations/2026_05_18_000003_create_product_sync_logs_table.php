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
        Schema::create('product_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id')->nullable()->index();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('conflict_id')->nullable();
            $table->enum('action', [
                'sync_started',
                'sync_completed',
                'product_created',
                'product_updated',
                'conflict_detected',
                'conflict_updated',
                'conflict_resolved',
                'conflict_ignored',
                'shopify_update_failed',
                'ps_restful_fetch_failed',
                'sync_failed'
            ])->index();
            $table->enum('status', ['success', 'failed', 'warning', 'info'])->default('info')->index();
            $table->text('message')->nullable();
            $table->json('payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            // Composite indexes
            $table->index(['shop_id', 'action']);
            $table->index(['shop_id', 'status']);
            $table->index(['created_at']);
            
            // Foreign keys
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onDelete('cascade');
            
            $table->foreign('conflict_id')
                ->references('id')
                ->on('product_sync_conflicts')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_sync_logs');
    }
};
