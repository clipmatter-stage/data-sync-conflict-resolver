<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductSyncConflict extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'product_id',
        'ps_product_id',
        'shopify_product_id',
        'field_name',
        'ps_value',
        'shopify_value',
        'resolved_value',
        'status',
        'resolution_source',
        'detected_at',
        'resolved_at',
        'resolved_by',
    ];

    protected $casts = [
        'detected_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    /**
     * Get the product that owns this conflict
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get all logs for this conflict
     */
    public function logs(): HasMany
    {
        return $this->hasMany(ProductSyncLog::class, 'conflict_id');
    }

    /**
     * Check if conflict is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if conflict is resolved
     */
    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }

    /**
     * Check if conflict is ignored
     */
    public function isIgnored(): bool
    {
        return $this->status === 'ignored';
    }

    /**
     * Mark conflict as resolved
     */
    public function markAsResolved(string $resolutionSource, $resolvedValue, ?string $resolvedBy = null): void
    {
        $this->update([
            'status' => 'resolved',
            'resolution_source' => $resolutionSource,
            'resolved_value' => $resolvedValue,
            'resolved_at' => now(),
            'resolved_by' => $resolvedBy,
        ]);
    }

    /**
     * Mark conflict as ignored
     */
    public function markAsIgnored(?string $resolvedBy = null): void
    {
        $this->update([
            'status' => 'ignored',
            'resolution_source' => 'ignored',
            'resolved_at' => now(),
            'resolved_by' => $resolvedBy,
        ]);
    }

    /**
     * Reopen conflict with new values
     */
    public function reopen(string $psValue, string $shopifyValue): void
    {
        $this->update([
            'ps_value' => $psValue,
            'shopify_value' => $shopifyValue,
            'status' => 'pending',
            'resolution_source' => null,
            'resolved_value' => null,
            'resolved_at' => null,
            'resolved_by' => null,
            'detected_at' => now(),
        ]);
    }

    /**
     * Scope for pending conflicts
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for resolved conflicts
     */
    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    /**
     * Scope for ignored conflicts
     */
    public function scopeIgnored($query)
    {
        return $query->where('status', 'ignored');
    }
}
