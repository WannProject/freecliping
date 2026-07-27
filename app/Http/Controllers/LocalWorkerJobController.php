<?php

namespace App\Http\Controllers;

use App\Enums\ClipAspectRatio;
use App\Enums\ClipQuality;
use App\Enums\LocalWorkerJobStatus;
use App\Enums\SubtitleStyle;
use App\Http\Requests\StoreLocalWorkerJobRequest;
use App\Http\Requests\UpdateLocalWorkerJobRequest;
use App\Models\LocalWorkerJob;
use App\Support\Clips\YouTubeUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class LocalWorkerJobController extends Controller
{
    public function store(StoreLocalWorkerJobRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $workerToken = Str::random(48);

        $job = LocalWorkerJob::query()->create([
            'source_type' => 'youtube',
            'source_url' => $validated['url'],
            'youtube_video_id' => YouTubeUrl::videoId($validated['url']),
            'title' => Arr::get($validated, 'title'),
            'channel' => Arr::get($validated, 'channel'),
            'duration_seconds' => Arr::get($validated, 'duration_seconds'),
            'start_seconds' => (int) $validated['start_seconds'],
            'end_seconds' => (int) $validated['end_seconds'],
            'aspect_ratio' => $request->enum('aspect_ratio', ClipAspectRatio::class) ?? ClipAspectRatio::Original,
            'quality' => $request->enum('quality', ClipQuality::class) ?? ClipQuality::Source,
            'subtitles_enabled' => (bool) $request->boolean('subtitles_enabled'),
            'subtitle_style' => $request->enum('subtitle_style', SubtitleStyle::class) ?? SubtitleStyle::WordHighlight,
            'subtitle_font_family' => Arr::get($validated, 'subtitle_font_family', 'dejavu-sans'),
            'subtitle_font_size' => Arr::get($validated, 'subtitle_font_size', 'medium'),
            'subtitle_position' => Arr::get($validated, 'subtitle_position', 'bottom'),
            'subtitle_color' => Arr::get($validated, 'subtitle_color', 'white'),
            'sync_output' => (bool) $request->boolean('sync_output'),
            'status' => LocalWorkerJobStatus::Queued,
            'progress' => 0,
            'worker_token_hash' => hash('sha256', $workerToken),
            'requested_ip' => $request->ip(),
        ]);

        $job->update([
            'manifest' => $this->manifestFor($job),
        ]);

        return response()->json([
            'localWorkerJob' => $this->jobPayload($job->refresh(), $workerToken),
        ], 202);
    }

    public function show(LocalWorkerJob $localWorkerJob): JsonResponse
    {
        return response()->json([
            'localWorkerJob' => $this->jobPayload($localWorkerJob),
        ]);
    }

    public function update(UpdateLocalWorkerJobRequest $request, LocalWorkerJob $localWorkerJob): JsonResponse
    {
        if (! $localWorkerJob->matchesWorkerToken($request->validated('token'))) {
            return response()->json([
                'message' => 'Token local worker tidak valid.',
            ], 403);
        }

        $status = LocalWorkerJobStatus::from($request->validated('status'));
        $isTerminal = in_array($status, [
            LocalWorkerJobStatus::Completed,
            LocalWorkerJobStatus::Failed,
            LocalWorkerJobStatus::Cancelled,
        ], true);

        $localWorkerJob->update([
            'status' => $status,
            'progress' => (int) $request->validated('progress'),
            'local_output_path' => $request->validated('local_output_path'),
            'error_message' => $request->validated('error_message'),
            'completed_at' => $isTerminal ? now() : $localWorkerJob->completed_at,
        ]);

        return response()->json([
            'localWorkerJob' => $this->jobPayload($localWorkerJob->refresh()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function jobPayload(LocalWorkerJob $job, ?string $workerToken = null): array
    {
        return [
            'uuid' => $job->uuid,
            'status' => $job->status->value,
            'progress' => $job->progress,
            'errorMessage' => $job->error_message,
            'localOutputPath' => $job->local_output_path,
            'statusUrl' => route('local-worker-jobs.show', $job),
            'manifest' => $this->manifestFor($job, $workerToken),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function manifestFor(LocalWorkerJob $job, ?string $workerToken = null): array
    {
        $manifest = [
            'version' => 1,
            'runner' => 'freekliping-local-worker',
            'jobId' => $job->uuid,
            'source' => [
                'type' => $job->source_type,
                'url' => $job->source_url,
                'youtubeVideoId' => $job->youtube_video_id,
                'cookiePolicy' => 'local-only-never-send-to-server',
            ],
            'clip' => [
                'startSeconds' => $job->start_seconds,
                'endSeconds' => $job->end_seconds,
                'durationSeconds' => $job->end_seconds - $job->start_seconds,
            ],
            'export' => [
                'aspectRatio' => $job->aspect_ratio->value,
                'quality' => $job->quality->value,
                'format' => 'mp4',
                'subtitlesEnabled' => $job->subtitles_enabled,
                'subtitleStyle' => $job->subtitles_enabled ? $job->subtitle_style->value : 'off',
                'subtitleFontFamily' => $job->subtitle_font_family,
                'subtitleFontSize' => $job->subtitle_font_size,
                'subtitlePosition' => $job->subtitle_position,
                'subtitleColor' => $job->subtitle_color,
            ],
            'output' => [
                'defaultFileName' => sprintf(
                    'freekliping-%s-%ss-%ss.mp4',
                    $job->youtube_video_id ?? 'clip',
                    $job->start_seconds,
                    $job->end_seconds,
                ),
                'syncOutput' => $job->sync_output,
            ],
            'callbacks' => [
                'statusUrl' => route('local-worker-jobs.update', $job),
                'method' => 'PATCH',
            ],
            'requirements' => [
                'ffmpeg' => 'bundled-or-auto-detect',
                'ytDlp' => 'bundled-or-auto-detect',
                'credentials' => 'keep-platform-cookies-local',
            ],
            'disclaimer' => 'Local mode runs on the user device. The user is responsible for platform terms, content rights, and any local credentials used by the worker.',
        ];

        if ($workerToken !== null) {
            $manifest['callbacks']['token'] = $workerToken;
        }

        return $manifest;
    }
}
