<?php

namespace App\Http\Controllers\ProductSync;

use App\Http\Controllers\Controller;
use App\Services\ProductSync\ProductConflictService;
use App\Services\ProductSync\ProductSyncLogService;
use App\Services\ProductSync\ProductSyncService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProductSyncDashboardController extends Controller
{
    protected ProductSyncService $syncService;
    protected ProductConflictService $conflictService;
    protected ProductSyncLogService $logService;

    public function __construct(
        ProductSyncService $syncService,
        ProductConflictService $conflictService,
        ProductSyncLogService $logService
    ) {
        $this->syncService = $syncService;
        $this->conflictService = $conflictService;
        $this->logService = $logService;
    }

    /**
     * Display the product sync dashboard
     */
    public function index(): Response
    {
        $shop = Auth::user();
        $shopId = $shop?->id;

        // Get sync statistics
        $stats = $this->syncService->getSyncStats($shopId);

        // Get recent pending conflicts
        $recentConflicts = $this->conflictService->getPendingConflicts($shopId)
            ->take(10)
            ->map(function ($conflict) {
                return [
                    'id' => $conflict->id,
                    'product' => [
                        'id' => $conflict->product->id,
                        'title' => $conflict->product->title,
                        'sku' => $conflict->product->sku,
                    ],
                    'field_name' => $conflict->field_name,
                    'ps_value' => $conflict->ps_value,
                    'shopify_value' => $conflict->shopify_value,
                    'status' => $conflict->status,
                    'detected_at' => $conflict->detected_at?->toISOString(),
                ];
            });

        // Get recent logs
        $recentLogs = $this->logService->getRecentLogs(10, $shopId)
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'status' => $log->status,
                    'message' => $log->message,
                    'created_at' => $log->created_at?->toISOString(),
                ];
            });

        return Inertia::render('ProductSync/Dashboard', [
            'stats' => $stats,
            'recentConflicts' => $recentConflicts,
            'recentLogs' => $recentLogs,
        ]);
    }
}
