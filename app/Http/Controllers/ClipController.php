<?php

namespace App\Http\Controllers;

use App\Enums\ClipAspectRatio;
use App\Enums\ClipQuality;
use App\Enums\ClipStatus;
use App\Enums\SubtitleStyle;
use App\Http\Requests\StoreClipMetadataRequest;
use App\Http\Requests\StoreClipRequest;
use App\Http\Requests\UpdateClipFilenameRequest;
use App\Jobs\ProcessClip;
use App\Models\Clip;
use App\Support\Clips\YouTubeMetadataClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClipController extends Controller
{
    public function metadata(StoreClipMetadataRequest $request, YouTubeMetadataClient $metadataClient): JsonResponse
    {
        try {
            $video = $metadataClient->fetch($request->validated('url'));
        } catch (RuntimeException $exception) {
            $status = str_contains($exception->getMessage(), 'dikonfigurasi') ? 503 : 422;

            return response()->json([
                'message' => $exception->getMessage(),
            ], $status);
        }

        return response()->json([
            'video' => $video->toArray(),
            'limits' => [
                'maxClipLength' => config('freekliping.max_clip_length'),
                'retentionHours' => config('freekliping.retention_hours'),
            ],
        ]);
    }

    public function store(StoreClipRequest $request, YouTubeMetadataClient $metadataClient): JsonResponse
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

        $startSeconds = (int) $request->validated('start_seconds');
        $endSeconds = (int) $request->validated('end_seconds');

        if ($endSeconds > $video->durationSeconds) {
            return response()->json([
                'message' => 'Titik akhir klip melewati durasi video.',
            ], 422);
        }

        $clip = Clip::create([
            'source_url' => $request->validated('url'),
            'youtube_video_id' => $video->id,
            'title' => $video->title,
            'channel' => $video->channel,
            'duration_seconds' => $video->durationSeconds,
            'start_seconds' => $startSeconds,
            'end_seconds' => $endSeconds,
            'aspect_ratio' => $request->enum('aspect_ratio', ClipAspectRatio::class) ?? ClipAspectRatio::Original,
            'quality' => $request->enum('quality', ClipQuality::class) ?? ClipQuality::Source,
            'subtitles_enabled' => (bool) $request->boolean('subtitles_enabled'),
            'subtitle_style' => $request->enum('subtitle_style', SubtitleStyle::class) ?? SubtitleStyle::WordHighlight,
            'subtitle_font_family' => $request->validated('subtitle_font_family', 'dejavu-sans'),
            'subtitle_font_size' => $request->validated('subtitle_font_size', 'medium'),
            'subtitle_position' => $request->validated('subtitle_position', 'bottom'),
            'subtitle_color' => $request->validated('subtitle_color', 'white'),
            'status' => ClipStatus::Queued,
            'progress' => 5,
            'requested_ip' => $request->ip(),
        ]);

        ProcessClip::dispatch($clip->id);

        return response()->json([
            'clip' => $this->clipPayload($clip),
        ], 202);
    }

    public function show(Clip $clip): JsonResponse
    {
        return response()->json([
            'clip' => $this->clipPayload($clip),
        ]);
    }

    public function updateFilename(UpdateClipFilenameRequest $request, Clip $clip): JsonResponse
    {
        if ($clip->status !== ClipStatus::Completed) {
            return response()->json([
                'message' => 'Nama file hanya bisa diubah setelah klip selesai diproses.',
            ], 409);
        }

        $fileName = $this->normalizeFileName($request->validated('file_name'));

        if ($fileName === '') {
            return response()->json([
                'message' => 'Nama file tidak valid.',
            ], 422);
        }

        $clip->update([
            'custom_file_name' => $fileName,
        ]);

        return response()->json([
            'clip' => $this->clipPayload($clip->refresh()),
        ]);
    }

    public function download(Request $request, Clip $clip): StreamedResponse
    {
        abort_unless($clip->status === ClipStatus::Completed, 404);
        abort_unless($clip->output_path && $clip->output_disk, 404);
        abort_if($clip->output_expires_at?->isPast(), 404);

        return Storage::disk($clip->output_disk)->download(
            $clip->output_path,
            $clip->fileName(),
        );
    }

    public function preview(Request $request, Clip $clip): StreamedResponse
    {
        abort_unless($clip->status === ClipStatus::Completed, 404);
        abort_unless($clip->output_path && $clip->output_disk, 404);
        abort_if($clip->output_expires_at?->isPast(), 404);

        return Storage::disk($clip->output_disk)->download(
            $clip->output_path,
            $clip->fileName(),
            [
                'Content-Disposition' => 'inline; filename="'.$clip->fileName().'"',
                'Content-Type' => 'video/mp4',
            ],
        );
    }

    /**
     * Reject new clips before they reach yt-dlp when capacity is saturated.
     * Returns a 429 when the IP has too many in-flight clips, or a 503 when
     * the global queue is overloaded.
     */
    private function capacityResponse(Request $request): ?JsonResponse
    {
        $pendingForIp = Clip::query()->pendingForIp($request->ip())->count();

        if ($pendingForIp >= (int) config('freekliping.max_pending_per_ip', 3)) {
            return response()->json([
                'message' => 'Kamu masih punya klip yang sedang diproses. Tunggu sampai selesai sebelum membuat klip baru.',
            ], 429);
        }

        $pending = Clip::query()->pending()->count();

        if ($pending >= (int) config('freekliping.max_concurrent_clips', 10)) {
            return response()->json([
                'message' => 'Server sedang menangani banyak permintaan. Coba lagi dalam beberapa saat.',
            ], 503);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function clipPayload(Clip $clip): array
    {
        return [
            'uuid' => $clip->uuid,
            'status' => $clip->status->value,
            'progress' => $clip->progress,
            'queuedSeconds' => $this->queuedSeconds($clip),
            'errorMessage' => $clip->error_message,
            'fileName' => $clip->fileName(),
            'aspectRatio' => $clip->aspect_ratio->value,
            'quality' => $clip->quality->value,
            'duration' => $clip->end_seconds - $clip->start_seconds,
            'subtitleStatus' => $clip->subtitle_status?->value,
            'subtitleStyle' => $clip->subtitle_style->value,
            'subtitleFontFamily' => $clip->subtitle_font_family,
            'subtitleFontSize' => $clip->subtitle_font_size,
            'subtitlePosition' => $clip->subtitle_position,
            'subtitleColor' => $clip->subtitle_color,
            'sizeMb' => $clip->output_size_bytes
                ? round($clip->output_size_bytes / 1024 / 1024, 1)
                : null,
            'downloadUrl' => $clip->downloadUrl(),
            'previewUrl' => $clip->previewUrl(),
            'statusUrl' => route('clips.show', $clip),
        ];
    }

    private function queuedSeconds(Clip $clip): int
    {
        if ($clip->status !== ClipStatus::Queued || ! $clip->created_at) {
            return 0;
        }

        return max(0, (int) $clip->created_at->diffInSeconds(now()));
    }

    private function normalizeFileName(string $fileName): string
    {
        return str($fileName)
            ->replaceMatches('/\.mp4$/i', '')
            ->replaceMatches('/[^A-Za-z0-9 ._-]+/', '')
            ->replaceMatches('/\s+/', ' ')
            ->trim(' ._-')
            ->limit(120, '')
            ->toString();
    }
}
