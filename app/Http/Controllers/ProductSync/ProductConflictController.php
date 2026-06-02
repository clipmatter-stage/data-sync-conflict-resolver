<?php

namespace App\Http\Controllers\ProductSync;

use App\Http\Controllers\Controller;
use App\Jobs\ResolveProductConflictJob;
use App\Models\ProductSyncConflict;
use App\Services\ProductSync\ProductConflictResolverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ProductConflictController extends Controller
{
    protected ProductConflictResolverService $resolverService;

    public function __construct(ProductConflictResolverService $resolverService)
    {
        $this->resolverService = $resolverService;
    }

    /**
     * Display a listing of conflicts
     */
    public function index(Request $request): Response
    {
        $shop = Auth::user();
        $shopId = $shop?->id;

        $query = ProductSyncConflict::query()
            ->with('product')
            ->when($shopId, fn($q) => $q->where('shop_id', $shopId));

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('field_name')) {
            $query->where('field_name', $request->input('field_name'));
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        // Pagination
        $conflicts = $query->latest('detected_at')
            ->paginate(20)
            ->through(function ($conflict) {
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
                    'resolved_value' => $conflict->resolved_value,
                    'status' => $conflict->status,
                    'resolution_source' => $conflict->resolution_source,
                    'detected_at' => $conflict->detected_at?->toISOString(),
                    'resolved_at' => $conflict->resolved_at?->toISOString(),
                ];
            });

        return Inertia::render('ProductSync/Conflicts/Index', [
            'conflicts' => $conflicts,
            'filters' => $request->only(['status', 'field_name', 'search']),
        ]);
    }

    /**
     * Display a specific conflict
     */
    public function show(ProductSyncConflict $conflict): Response
    {
        $conflict->load('product');

        return Inertia::render('ProductSync/Conflicts/Show', [
            'conflict' => [
                'id' => $conflict->id,
                'product' => [
                    'id' => $conflict->product->id,
                    'title' => $conflict->product->title,
                    'sku' => $conflict->product->sku,
                    'description' => $conflict->product->description,
                    'vendor' => $conflict->product->vendor,
                    'product_type' => $conflict->product->product_type,
                    'price' => $conflict->product->price,
                    'compare_at_price' => $conflict->product->compare_at_price,
                    'inventory_quantity' => $conflict->product->inventory_quantity,
                    'status' => $conflict->product->status,
                    'tags' => $conflict->product->tags,
                    'image_urls' => $conflict->product->image_urls,
                ],
                'field_name' => $conflict->field_name,
                'ps_value' => $conflict->ps_value,
                'shopify_value' => $conflict->shopify_value,
                'resolved_value' => $conflict->resolved_value,
                'status' => $conflict->status,
                'resolution_source' => $conflict->resolution_source,
                'detected_at' => $conflict->detected_at?->toISOString(),
                'resolved_at' => $conflict->resolved_at?->toISOString(),
                'ps_payload' => $conflict->product->raw_ps_payload,
                'shopify_payload' => $conflict->product->raw_shopify_payload,
            ],
        ]);
    }

    /**
     * Resolve a conflict
     */
    public function resolve(Request $request, ProductSyncConflict $conflict): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'resolution_source' => 'required|in:ps_restful,shopify,custom,ignored',
            'custom_value' => 'nullable',
        ]);

        if ($validated['resolution_source'] === 'custom' && !isset($validated['custom_value'])) {
            return $this->errorResponse(
                $request,
                'Custom value is required for custom resolution',
                422,
                ['custom_value' => 'Custom value is required for custom resolution']
            );
        }

        if ($conflict->status !== 'pending') {
            return $this->errorResponse(
                $request,
                'Only pending conflicts can be resolved',
                422,
                ['conflict' => 'Only pending conflicts can be resolved']
            );
        }

        try {
            $shop = Auth::user();
            $resolvedBy = $shop?->name ?? 'system';

            // Dispatch job to resolve conflict
            ResolveProductConflictJob::dispatch(
                $conflict->id,
                $validated['resolution_source'],
                $validated['custom_value'] ?? null,
                $resolvedBy
            );

            return $this->successResponse(
                $request,
                'Conflict resolution started. This may take a moment.'
            );

        } catch (\Exception $e) {
            Log::error('Failed to resolve conflict', [
                'conflict_id' => $conflict->id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                $request,
                'Failed to resolve conflict',
                500,
                [],
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Ignore a conflict
     */
    public function ignore(Request $request, ProductSyncConflict $conflict): JsonResponse|RedirectResponse
    {
        if ($conflict->status !== 'pending') {
            return $this->errorResponse(
                $request,
                'Only pending conflicts can be ignored',
                422,
                ['conflict' => 'Only pending conflicts can be ignored']
            );
        }

        try {
            $shop = Auth::user();
            $resolvedBy = $shop?->name ?? 'system';

            // Dispatch job to ignore conflict
            ResolveProductConflictJob::dispatch(
                $conflict->id,
                'ignored',
                null,
                $resolvedBy
            );

            return $this->successResponse($request, 'Conflict ignored successfully');

        } catch (\Exception $e) {
            Log::error('Failed to ignore conflict', [
                'conflict_id' => $conflict->id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                $request,
                'Failed to ignore conflict',
                500,
                [],
                ['error' => $e->getMessage()]
            );
        }
    }

    private function successResponse(Request $request, string $message): JsonResponse|RedirectResponse
    {
        if ($request->header('X-Inertia')) {
            return back(303)->with('success', $message);
        }

        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
    }

    private function errorResponse(
        Request $request,
        string $message,
        int $status = 500,
        array $validationErrors = [],
        array $extra = []
    ): JsonResponse|RedirectResponse {
        if ($request->header('X-Inertia')) {
            $redirect = back(303)->with('error', $message);

            if ($validationErrors !== []) {
                return $redirect->withErrors($validationErrors);
            }

            return $redirect;
        }

        return response()->json(array_merge([
            'success' => false,
            'message' => $message,
        ], $extra), $status);
    }
}
