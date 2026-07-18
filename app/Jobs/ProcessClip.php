<?php

namespace App\Jobs;

use App\Enums\ClipStatus;
use App\Models\Clip;
use App\Support\Clips\ClipProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessClip implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 600;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30];

    public function __construct(public int $clipId) {}

    /**
     * Execute the job.
     */
    public function handle(ClipProcessor $processor): void
    {
        $clip = Clip::query()->findOrFail($this->clipId);

        $clip->update([
            'status' => ClipStatus::Processing,
            'progress' => 20,
            'error_message' => null,
        ]);

        $processor->process($clip);
    }

    public function failed(?Throwable $exception): void
    {
        Clip::query()
            ->whereKey($this->clipId)
            ->update([
                'status' => ClipStatus::Failed,
                'progress' => 100,
                'error_message' => $exception?->getMessage() ?: 'Clip gagal diproses.',
            ]);
    }
}
