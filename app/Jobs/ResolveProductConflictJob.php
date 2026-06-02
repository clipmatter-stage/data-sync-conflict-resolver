<?php

namespace App\Jobs;

use App\Models\ProductSyncConflict;
use App\Services\ProductSync\ProductConflictResolverService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ResolveProductConflictJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $conflictId;
    public string $resolutionSource;
    public $customValue;
    public ?string $resolvedBy;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        int $conflictId,
        string $resolutionSource,
        $customValue = null,
        ?string $resolvedBy = null
    ) {
        $this->conflictId = $conflictId;
        $this->resolutionSource = $resolutionSource;
        $this->customValue = $customValue;
        $this->resolvedBy = $resolvedBy;
    }

    /**
     * Execute the job.
     */
    public function handle(ProductConflictResolverService $resolverService): void
    {
        Log::info('ResolveProductConflictJob started', [
            'conflict_id' => $this->conflictId,
            'resolution_source' => $this->resolutionSource,
        ]);

        try {
            $conflict = ProductSyncConflict::find($this->conflictId);

            if (!$conflict) {
                Log::error('ResolveProductConflictJob: Conflict not found', [
                    'conflict_id' => $this->conflictId,
                ]);
                return;
            }

            if ($conflict->status !== 'pending') {
                Log::warning('ResolveProductConflictJob: Conflict is not pending', [
                    'conflict_id' => $this->conflictId,
                    'status' => $conflict->status,
                ]);
                return;
            }

            $result = $resolverService->resolveConflict(
                $conflict,
                $this->resolutionSource,
                $this->customValue,
                $this->resolvedBy
            );

            if ($result['success']) {
                Log::info('ResolveProductConflictJob completed successfully', [
                    'conflict_id' => $this->conflictId,
                    'updated_shopify' => $result['updated_shopify'] ?? false,
                ]);
            } else {
                Log::error('ResolveProductConflictJob completed with errors', [
                    'conflict_id' => $this->conflictId,
                    'error' => $result['error'] ?? 'Unknown error',
                ]);
            }

        } catch (\Exception $e) {
            Log::error('ResolveProductConflictJob failed with exception', [
                'conflict_id' => $this->conflictId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ResolveProductConflictJob failed permanently', [
            'conflict_id' => $this->conflictId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // Mark conflict as failed
        $conflict = ProductSyncConflict::find($this->conflictId);
        if ($conflict) {
            $conflict->update(['status' => 'failed']);
        }
    }
}
