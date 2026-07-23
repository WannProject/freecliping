<?php

namespace App\Jobs;

use App\Enums\ClipAnalysisStatus;
use App\Models\ClipAnalysis;
use App\Support\Clips\ClipMomentRecommender;
use App\Support\Clips\YouTubeTranscriptClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessClipAnalysis implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    /**
     * @var array<int, int>
     */
    public array $backoff = [5, 15];

    public function __construct(public int $analysisId) {}

    /**
     * Execute the job.
     */
    public function handle(YouTubeTranscriptClient $transcriptClient, ClipMomentRecommender $recommender): void
    {
        $analysis = ClipAnalysis::query()->findOrFail($this->analysisId);
        $workDirectory = storage_path("app/clip-analysis/{$analysis->uuid}");

        $analysis->update([
            'status' => ClipAnalysisStatus::Processing,
            'progress' => 20,
            'error_message' => null,
        ]);

        Log::info('clip analysis started', ['analysis' => $analysis->uuid]);

        try {
            $transcript = $transcriptClient->fetchJson3($analysis->source_url, $workDirectory);

            $analysis->update(['progress' => 55]);

            $recommendations = $recommender->recommendFromJson3(
                content: $transcript['content'],
                durationSeconds: $analysis->duration_seconds,
            );

            if ($recommendations === []) {
                throw new \RuntimeException('Transcript ditemukan, tetapi tidak ada kandidat klip yang cukup kuat.');
            }

            $analysis->update([
                'status' => ClipAnalysisStatus::Completed,
                'progress' => 100,
                'transcript_language' => $transcript['language'],
                'recommendations' => $recommendations,
                'completed_at' => now(),
            ]);

            Log::info('clip analysis completed', [
                'analysis' => $analysis->uuid,
                'recommendations' => count($recommendations),
            ]);
        } finally {
            File::deleteDirectory($workDirectory);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $analysis = ClipAnalysis::query()->find($this->analysisId);

        Log::error('clip analysis failed', [
            'analysis' => $analysis?->uuid,
            'error' => $exception?->getMessage(),
        ]);

        ClipAnalysis::query()
            ->whereKey($this->analysisId)
            ->update([
                'status' => ClipAnalysisStatus::Failed,
                'progress' => 100,
                'error_message' => $exception?->getMessage() ?: 'Analisis video gagal diproses.',
            ]);
    }
}
