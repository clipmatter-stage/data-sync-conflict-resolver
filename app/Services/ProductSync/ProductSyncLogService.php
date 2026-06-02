<?php

namespace App\Services\ProductSync;

use App\Models\ProductSyncLog;

class ProductSyncLogService
{
    /**
     * Create a sync log
     *
     * @param array $data
     * @return ProductSyncLog
     */
    public function createLog(array $data): ProductSyncLog
    {
        return ProductSyncLog::create([
            'shop_id' => $data['shop_id'] ?? null,
            'product_id' => $data['product_id'] ?? null,
            'conflict_id' => $data['conflict_id'] ?? null,
            'action' => $data['action'],
            'status' => $data['status'] ?? 'info',
            'message' => $data['message'] ?? null,
            'payload' => $data['payload'] ?? null,
            'error_message' => $data['error_message'] ?? null,
        ]);
    }

    /**
     * Log sync started
     */
    public function logSyncStarted(?int $shopId = null): ProductSyncLog
    {
        return $this->createLog([
            'shop_id' => $shopId,
            'action' => 'sync_started',
            'status' => 'info',
            'message' => 'Product sync started',
        ]);
    }

    /**
     * Log sync completed
     */
    public function logSyncCompleted(array $summary, ?int $shopId = null): ProductSyncLog
    {
        return $this->createLog([
            'shop_id' => $shopId,
            'action' => 'sync_completed',
            'status' => 'success',
            'message' => 'Product sync completed successfully',
            'payload' => $summary,
        ]);
    }

    /**
     * Log sync failed
     */
    public function logSyncFailed(string $error, ?int $shopId = null): ProductSyncLog
    {
        return $this->createLog([
            'shop_id' => $shopId,
            'action' => 'sync_failed',
            'status' => 'failed',
            'message' => 'Product sync failed',
            'error_message' => $error,
        ]);
    }

    /**
     * Log product created
     */
    public function logProductCreated(int $productId, array $productData, ?int $shopId = null): ProductSyncLog
    {
        return $this->createLog([
            'shop_id' => $shopId,
            'product_id' => $productId,
            'action' => 'product_created',
            'status' => 'success',
            'message' => "Product created in Shopify: {$productData['title']}",
            'payload' => $productData,
        ]);
    }

    /**
     * Log product updated
     */
    public function logProductUpdated(int $productId, array $productData, ?int $shopId = null): ProductSyncLog
    {
        return $this->createLog([
            'shop_id' => $shopId,
            'product_id' => $productId,
            'action' => 'product_updated',
            'status' => 'success',
            'message' => "Product updated: {$productData['title']}",
            'payload' => $productData,
        ]);
    }

    /**
     * Log conflict detected
     */
    public function logConflictDetected(int $conflictId, int $productId, string $fieldName, ?int $shopId = null): ProductSyncLog
    {
        return $this->createLog([
            'shop_id' => $shopId,
            'product_id' => $productId,
            'conflict_id' => $conflictId,
            'action' => 'conflict_detected',
            'status' => 'warning',
            'message' => "Conflict detected for field: {$fieldName}",
        ]);
    }

    /**
     * Log conflict updated
     */
    public function logConflictUpdated(int $conflictId, int $productId, string $fieldName, ?int $shopId = null): ProductSyncLog
    {
        return $this->createLog([
            'shop_id' => $shopId,
            'product_id' => $productId,
            'conflict_id' => $conflictId,
            'action' => 'conflict_updated',
            'status' => 'warning',
            'message' => "Conflict updated for field: {$fieldName}",
        ]);
    }

    /**
     * Log conflict resolved
     */
    public function logConflictResolved(
        int $conflictId,
        int $productId,
        string $fieldName,
        string $resolutionSource,
        ?int $shopId = null
    ): ProductSyncLog {
        return $this->createLog([
            'shop_id' => $shopId,
            'product_id' => $productId,
            'conflict_id' => $conflictId,
            'action' => 'conflict_resolved',
            'status' => 'success',
            'message' => "Conflict resolved for field: {$fieldName} using {$resolutionSource}",
        ]);
    }

    /**
     * Log conflict ignored
     */
    public function logConflictIgnored(int $conflictId, int $productId, string $fieldName, ?int $shopId = null): ProductSyncLog
    {
        return $this->createLog([
            'shop_id' => $shopId,
            'product_id' => $productId,
            'conflict_id' => $conflictId,
            'action' => 'conflict_ignored',
            'status' => 'info',
            'message' => "Conflict ignored for field: {$fieldName}",
        ]);
    }

    /**
     * Log Shopify update failed
     */
    public function logShopifyUpdateFailed(int $productId, string $error, ?int $shopId = null): ProductSyncLog
    {
        return $this->createLog([
            'shop_id' => $shopId,
            'product_id' => $productId,
            'action' => 'shopify_update_failed',
            'status' => 'failed',
            'message' => 'Failed to update Shopify product',
            'error_message' => $error,
        ]);
    }

    /**
     * Log PS RESTful fetch failed
     */
    public function logPsRestfulFetchFailed(string $error, ?int $shopId = null): ProductSyncLog
    {
        return $this->createLog([
            'shop_id' => $shopId,
            'action' => 'ps_restful_fetch_failed',
            'status' => 'failed',
            'message' => 'Failed to fetch products from PS RESTful',
            'error_message' => $error,
        ]);
    }

    /**
     * Get recent logs
     */
    public function getRecentLogs(int $limit = 20, ?int $shopId = null)
    {
        $query = ProductSyncLog::query()
            ->with(['product', 'conflict'])
            ->latest();

        if ($shopId) {
            $query->where('shop_id', $shopId);
        }

        return $query->limit($limit)->get();
    }

    /**
     * Get logs by action
     */
    public function getLogsByAction(string $action, ?int $shopId = null)
    {
        $query = ProductSyncLog::query()
            ->with(['product', 'conflict'])
            ->where('action', $action)
            ->latest();

        if ($shopId) {
            $query->where('shop_id', $shopId);
        }

        return $query->get();
    }
}
