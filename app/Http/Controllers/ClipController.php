<?php

namespace App\Http\Controllers;

use App\Enums\ClipAspectRatio;
use App\Enums\ClipQuality;
use App\Enums\ClipStatus;
use App\Http\Requests\UpdateClipFilenameRequest;
use App\Http\Requests\StoreClipMetadataRequest;
use App\Http\Requests\StoreClipRequest;
use App\Jobs\ProcessClip;
use App\Models\Clip;
use App\Support\Clips\YouTubeMetadataClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

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

    public function download(Request $request, Clip $clip)
    {
        abort_unless($clip->status === ClipStatus::Completed, 404);
        abort_unless($clip->output_path && $clip->output_disk, 404);
        abort_if($clip->output_expires_at?->isPast(), 404);

        return Storage::disk($clip->output_disk)->download(
            $clip->output_path,
            $clip->fileName(),
        );
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
            'errorMessage' => $clip->error_message,
            'fileName' => $clip->fileName(),
            'aspectRatio' => $clip->aspect_ratio->value,
            'quality' => $clip->quality->value,
            'duration' => $clip->end_seconds - $clip->start_seconds,
            'sizeMb' => $clip->output_size_bytes
                ? round($clip->output_size_bytes / 1024 / 1024, 1)
                : null,
            'downloadUrl' => $clip->downloadUrl(),
            'statusUrl' => route('clips.show', $clip),
        ];
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
