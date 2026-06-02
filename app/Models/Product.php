<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'ps_product_id',
        'shopify_product_id',
        'shopify_variant_id',
        'title',
        'sku',
        'description',
        'vendor',
        'product_type',
        'price',
        'compare_at_price',
        'inventory_quantity',
        'status',
        'tags',
        'image_urls',
        'raw_ps_payload',
        'raw_shopify_payload',
        'last_synced_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'inventory_quantity' => 'integer',
        'tags' => 'array',
        'image_urls' => 'array',
        'raw_ps_payload' => 'array',
        'raw_shopify_payload' => 'array',
        'last_synced_at' => 'datetime',
    ];

    /**
     * Get all conflicts for this product
     */
    public function conflicts(): HasMany
    {
        return $this->hasMany(ProductSyncConflict::class);
    }

    /**
     * Get pending conflicts for this product
     */
    public function pendingConflicts(): HasMany
    {
        return $this->hasMany(ProductSyncConflict::class)->where('status', 'pending');
    }

    /**
     * Get resolved conflicts for this product
     */
    public function resolvedConflicts(): HasMany
    {
        return $this->hasMany(ProductSyncConflict::class)->where('status', 'resolved');
    }

    /**
     * Get all sync logs for this product
     */
    public function syncLogs(): HasMany
    {
        return $this->hasMany(ProductSyncLog::class);
    }

    /**
     * Check if product has pending conflicts
     */
    public function hasPendingConflicts(): bool
    {
        return $this->pendingConflicts()->exists();
    }

    /**
     * Get the primary matching key for sync
     * Priority: SKU > PS Product ID > Shopify Product ID
     */
    public function getMatchingKey(): ?string
    {
        return $this->sku ?? $this->ps_product_id ?? $this->shopify_product_id;
    }
}
