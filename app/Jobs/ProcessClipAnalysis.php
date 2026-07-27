<?php

namespace App\Jobs;

use App\Enums\ClipAnalysisStatus;
use App\Models\ClipAnalysis;
use App\Support\Clips\ClipMomentRecommender;
use App\Support\Clips\WhisperTranscriber;
use App\Support\Clips\YouTubeTranscriptClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ProcessClipAnalysis implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 1900;

    /**
     * @var array<int, int>
     */
    public array $backoff = [5, 15];

    public function __construct(public int $analysisId) {}

    /**
     * Execute the job.
     */
    public function handle(YouTubeTranscriptClient $transcriptClient, WhisperTranscriber $whisperTranscriber, ClipMomentRecommender $recommender): void
    {
        $analysis = ClipAnalysis::query()->findOrFail($this->analysisId);
        $workDirectory = storage_path("app/clip-analysis/{$analysis->uuid}");

        if (in_array($analysis->status, [
            ClipAnalysisStatus::Completed,
            ClipAnalysisStatus::Failed,
            ClipAnalysisStatus::Cancelled,
        ], true)) {
            return;
        }

        $analysis->update([
            'status' => ClipAnalysisStatus::Processing,
            'progress' => 20,
            'error_message' => null,
        ]);

        $startedAt = microtime(true);

        Log::info('clip analysis started', ['analysis' => $analysis->uuid]);

        try {
            $transcriptSource = 'youtube';
            $transcriptStartedAt = microtime(true);

            try {
                $analysis->update(['progress' => 30]);

                $transcript = $transcriptClient->fetchJson3(
                    url: $analysis->source_url,
                    workDirectory: $workDirectory,
                    videoId: $analysis->youtube_video_id,
                );
            } catch (RuntimeException $exception) {
                if (! $whisperTranscriber->enabled()) {
                    throw $exception;
                }

                $transcriptSource = 'whisper';
                $analysis->update(['progress' => 35]);

                Log::info('falling back to whisper transcript', [
                    'analysis' => $analysis->uuid,
                    'reason' => $exception->getMessage(),
                ]);

                $transcript = $whisperTranscriber->transcribe($analysis, $workDirectory);
            }

            if ($this->analysisWasCancelled($analysis->id)) {
                return;
            }

            $analysis->update(['progress' => 65]);

            Log::info('clip analysis transcript ready', [
                'analysis' => $analysis->uuid,
                'source' => $transcriptSource,
                'language' => $transcript['language'],
                'bytes' => strlen($transcript['content']),
                'elapsed_ms' => $this->elapsedMilliseconds($transcriptStartedAt),
            ]);

            $recommendationsStartedAt = microtime(true);
            $analysis->update(['progress' => 75]);

            $recommendations = $recommender->recommendFromJson3(
                content: $transcript['content'],
                durationSeconds: $analysis->duration_seconds,
            );

            if ($this->analysisWasCancelled($analysis->id)) {
                return;
            }

            Log::info('clip analysis recommendations scored', [
                'analysis' => $analysis->uuid,
                'recommendations' => count($recommendations),
                'elapsed_ms' => $this->elapsedMilliseconds($recommendationsStartedAt),
            ]);

            if ($recommendations === []) {
                throw new RuntimeException('Transcript ditemukan, tetapi tidak ada kandidat klip yang cukup kuat.');
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
                'elapsed_ms' => $this->elapsedMilliseconds($startedAt),
            ]);
        } finally {
            File::deleteDirectory($workDirectory);
        }
    }

    private function elapsedMilliseconds(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    /**
     * @phpstan-impure
     */
    private function analysisWasCancelled(int $analysisId): bool
    {
        return ClipAnalysis::query()
            ->whereKey($analysisId)
            ->where('status', ClipAnalysisStatus::Cancelled)
            ->exists();
    }

    public function failed(?Throwable $exception): void
    {
        $analysis = ClipAnalysis::query()->find($this->analysisId);

        if ($analysis?->status === ClipAnalysisStatus::Cancelled) {
            return;
        }

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
