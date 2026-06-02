<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSyncLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'product_id',
        'conflict_id',
        'action',
        'status',
        'message',
        'payload',
        'error_message',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    /**
     * Get the product that owns this log
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the conflict that owns this log
     */
    public function conflict(): BelongsTo
    {
        return $this->belongsTo(ProductSyncConflict::class, 'conflict_id');
    }

    /**
     * Scope for success logs
     */
    public function scopeSuccess($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope for failed logs
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope for warning logs
     */
    public function scopeWarning($query)
    {
        return $query->where('status', 'warning');
    }

    /**
     * Scope for info logs
     */
    public function scopeInfo($query)
    {
        return $query->where('status', 'info');
    }

    /**
     * Scope by action
     */
    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope recent logs
     */
    public function scopeRecent($query, int $limit = 10)
    {
        return $query->latest()->limit($limit);
    }
}
