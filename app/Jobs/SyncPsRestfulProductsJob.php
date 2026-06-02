<?php

namespace App\Jobs;

use App\Services\ProductSync\ProductSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncPsRestfulProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public ?int $shopId;
    public array $options;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 600;

    /**
     * Create a new job instance.
     */
    public function __construct(?int $shopId = null, array $options = [])
    {
        $this->shopId = $shopId;
        $this->options = $options;
    }

    /**
     * Execute the job.
     */
    public function handle(ProductSyncService $syncService): void
    {
        Log::info('Akeneo Product Sync Job started', [
            'shop_id' => $this->shopId,
            'options' => $this->options,
        ]);

        try {
            $result = $syncService->syncProducts($this->shopId, $this->options);

            if ($result['success']) {
                Log::info('Akeneo Product Sync Job completed successfully', [
                    'shop_id' => $this->shopId,
                    'summary' => $result['summary'],
                ]);
            } else {
                Log::error('Akeneo Product Sync Job completed with errors', [
                    'shop_id' => $this->shopId,
                    'error' => $result['error'] ?? 'Unknown error',
                    'summary' => $result['summary'],
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Akeneo Product Sync Job failed with exception', [
                'shop_id' => $this->shopId,
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
        Log::error('Akeneo Product Sync Job failed permanently', [
            'shop_id' => $this->shopId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);
    }
}
