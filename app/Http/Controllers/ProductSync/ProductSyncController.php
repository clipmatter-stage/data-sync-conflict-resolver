<?php

namespace App\Http\Controllers\ProductSync;

use App\Http\Controllers\Controller;
use App\Jobs\SyncPsRestfulProductsJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ProductSyncController extends Controller
{
    /**
     * Start product sync from Akeneo PIM
     */
    public function sync(Request $request): Response
    {
        try {
            $shop = Auth::user();
            $shopId = $shop?->id;

            // Get optional sync parameters
            $options = $request->only([
                'page',
                'page_size',
                'supplier',
                'supplier_code',
            ]);

            Log::info('Starting product sync job', [
                'shop_id' => $shopId,
                'options' => $options,
            ]);

            // Dispatch sync job
            SyncPsRestfulProductsJob::dispatch($shopId, $options);

            return $this->respond(
                $request,
                [
                'success' => true,
                'message' => 'Product sync started successfully. This may take a few minutes.',
                ]
            );

        } catch (\Exception $e) {
            Log::error('Failed to start product sync', [
                'error' => $e->getMessage(),
            ]);

            return $this->respond(
                $request,
                [
                'success' => false,
                'message' => 'Failed to start product sync',
                'error' => $e->getMessage(),
                ],
                500
            );
        }
    }

    private function respond(Request $request, array $payload, int $status = 200): JsonResponse|RedirectResponse
    {
        if ($request->header('X-Inertia')) {
            if (($payload['success'] ?? false) === true) {
                return back(303)->with('success', $payload['message'] ?? 'Action completed successfully.');
            }

            return back(303)->with('error', $payload['message'] ?? 'Action failed.');
        }

        return response()->json($payload, $status);
    }
}
