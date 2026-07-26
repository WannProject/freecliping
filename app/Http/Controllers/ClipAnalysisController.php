<?php

namespace App\Http\Controllers;

use App\Enums\CaptionKind;
use App\Enums\ClipAnalysisStatus;
use App\Http\Requests\StoreClipAnalysisRequest;
use App\Jobs\ProcessClipAnalysis;
use App\Models\ClipAnalysis;
use App\Support\Clips\WhisperTranscriber;
use App\Support\Clips\YouTubeMetadataClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ClipAnalysisController extends Controller
{
    public function store(StoreClipAnalysisRequest $request, YouTubeMetadataClient $metadataClient, WhisperTranscriber $whisperTranscriber): JsonResponse
    {
        if ($overloaded = $this->capacityResponse($request)) {
            return $overloaded;
        }

        try {
            $video = $metadataClient->fetch($request->validated('url'));
        } catch (RuntimeException $exception) {
            $status = str_contains($exception->getMessage(), 'dikonfigurasi') ? 503 : 422;

            return response()->json([
                'message' => $exception->getMessage(),
            ], $status);
        }

        if (! $video->captions->available() && ! $whisperTranscriber->enabled()) {
            return response()->json([
                'message' => 'Video ini belum punya caption/transcript yang bisa dianalisis. Aktifkan Whisper untuk fallback transcription.',
            ], 422);
        }

        if ($video->durationSeconds > (int) config('freekliping.max_analysis_video_length', 7200)) {
            return response()->json([
                'message' => 'Video terlalu panjang untuk dianalisis pada MVP.',
            ], 422);
        }

        $cached = ClipAnalysis::query()
            ->where('youtube_video_id', $video->id)
            ->where('status', ClipAnalysisStatus::Completed->value)
            ->whereNotNull('recommendations')
            ->where('updated_at', '>=', now()->subHours(6))
            ->latest()
            ->first();

        if ($cached instanceof ClipAnalysis) {
            return response()->json([
                'analysis' => $this->analysisPayload($cached),
            ]);
        }

        $analysis = ClipAnalysis::query()->create([
            'source_url' => $request->validated('url'),
            'youtube_video_id' => $video->id,
            'title' => $video->title,
            'channel' => $video->channel,
            'duration_seconds' => $video->durationSeconds,
            'thumbnail_url' => $video->thumbnailUrl,
            'status' => ClipAnalysisStatus::Queued,
            'progress' => 5,
            'requested_ip' => $request->ip(),
        ]);

        ProcessClipAnalysis::dispatch($analysis->id);

        return response()->json([
            'analysis' => $this->analysisPayload($analysis),
        ], 202);
    }

    public function show(ClipAnalysis $analysis): JsonResponse
    {
        return response()->json([
            'analysis' => $this->analysisPayload($analysis),
        ]);
    }

    private function capacityResponse(Request $request): ?JsonResponse
    {
        $pendingForIp = ClipAnalysis::query()->pendingForIp($request->ip())->count();

        if ($pendingForIp >= (int) config('freekliping.max_pending_analyses_per_ip', 2)) {
            return response()->json([
                'message' => 'Kamu masih punya analisis video yang sedang diproses. Tunggu sampai selesai sebelum menganalisis video baru.',
            ], 429);
        }

        $pending = ClipAnalysis::query()->pending()->count();

        if ($pending >= (int) config('freekliping.max_concurrent_analyses', 8)) {
            return response()->json([
                'message' => 'Server sedang menganalisis banyak video. Coba lagi dalam beberapa saat.',
            ], 503);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function analysisPayload(ClipAnalysis $analysis): array
    {
        return [
            'uuid' => $analysis->uuid,
            'status' => $analysis->status->value,
            'progress' => $analysis->progress,
            'errorMessage' => $analysis->error_message,
            'transcriptLanguage' => $analysis->transcript_language,
            'recommendations' => $analysis->recommendations ?? [],
            'statusUrl' => route('clip-analyses.show', $analysis),
            'video' => [
                'id' => $analysis->youtube_video_id,
                'title' => $analysis->title,
                'channel' => $analysis->channel,
                'duration' => $analysis->duration_seconds,
                'thumbnailUrl' => $analysis->thumbnail_url,
                'captions' => [
                    'available' => true,
                    'kind' => CaptionKind::Auto->value,
                    'language' => $analysis->transcript_language,
                ],
            ],
        ];
    }
}
