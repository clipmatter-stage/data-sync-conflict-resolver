<?php

namespace App\Http\Controllers\ProductSync;

use App\Http\Controllers\Controller;
use App\Models\ProductSyncLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProductSyncLogController extends Controller
{
    /**
     * Display a listing of sync logs
     */
    public function index(Request $request): Response
    {
        $shop = Auth::user();
        $shopId = $shop?->id;

        $query = ProductSyncLog::query()
            ->with(['product', 'conflict'])
            ->when($shopId, fn($q) => $q->where('shop_id', $shopId));

        // Apply filters
        if ($request->has('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        // Pagination
        $logs = $query->latest()
            ->paginate(50)
            ->withQueryString()
            ->through(function ($log) {
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'status' => $log->status,
                    'message' => $log->message,
                    'error_message' => $log->error_message,
                    'product' => $log->product ? [
                        'id' => $log->product->id,
                        'title' => $log->product->title,
                        'sku' => $log->product->sku,
                    ] : null,
                    'conflict_id' => $log->conflict_id,
                    'payload' => $log->payload,
                    'created_at' => $log->created_at?->toISOString(),
                ];
            });

        return Inertia::render('ProductSync/Logs/Index', [
            'logs' => $logs,
            'filters' => $request->only(['action', 'status', 'date_from', 'date_to']),
        ]);
    }
}
