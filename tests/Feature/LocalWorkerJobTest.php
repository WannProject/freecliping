<?php

use App\Enums\LocalWorkerJobStatus;
use App\Models\LocalWorkerJob;

test('local worker job endpoint returns a runnable manifest with callback token', function () {
    $response = $this->postJson(route('local-worker-jobs.store'), [
        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'title' => 'Example video',
        'channel' => 'Example channel',
        'duration_seconds' => 300,
        'start_seconds' => 30,
        'end_seconds' => 60,
        'aspect_ratio' => '9:16',
        'quality' => '720p',
        'subtitles_enabled' => true,
        'subtitle_font_family' => 'impact',
        'subtitle_font_size' => 'large',
        'subtitle_position' => 'top',
        'subtitle_color' => 'cyan',
    ]);

    $response->assertAccepted()
        ->assertJsonPath('localWorkerJob.status', 'queued')
        ->assertJsonPath('localWorkerJob.progress', 0)
        ->assertJsonPath('localWorkerJob.manifest.runner', 'freekliping-local-worker')
        ->assertJsonPath('localWorkerJob.manifest.source.youtubeVideoId', 'dQw4w9WgXcQ')
        ->assertJsonPath('localWorkerJob.manifest.source.cookiePolicy', 'local-only-never-send-to-server')
        ->assertJsonPath('localWorkerJob.manifest.clip.durationSeconds', 30)
        ->assertJsonPath('localWorkerJob.manifest.export.aspectRatio', '9:16')
        ->assertJsonPath('localWorkerJob.manifest.export.subtitleStyle', 'word-highlight')
        ->assertJsonPath('localWorkerJob.manifest.export.subtitleFontFamily', 'impact')
        ->assertJsonPath('localWorkerJob.manifest.export.subtitleFontSize', 'large')
        ->assertJsonPath('localWorkerJob.manifest.export.subtitlePosition', 'top')
        ->assertJsonPath('localWorkerJob.manifest.export.subtitleColor', 'cyan')
        ->assertJsonPath('localWorkerJob.manifest.requirements.ffmpeg', 'bundled-or-auto-detect')
        ->assertJsonPath('localWorkerJob.manifest.callbacks.method', 'PATCH');

    $token = $response->json('localWorkerJob.manifest.callbacks.token');
    $job = LocalWorkerJob::query()->first();

    expect($token)->toBeString()
        ->and(strlen($token))->toBe(48)
        ->and($job)->not->toBeNull()
        ->and($job->status)->toBe(LocalWorkerJobStatus::Queued)
        ->and($job->subtitle_font_family)->toBe('impact')
        ->and($job->subtitle_font_size)->toBe('large')
        ->and($job->subtitle_position)->toBe('top')
        ->and($job->subtitle_color)->toBe('cyan')
        ->and($job->worker_token_hash)->not->toBe($token)
        ->and($job->manifest['callbacks'])->not->toHaveKey('token');
});

test('local worker job endpoint rejects invalid youtube urls', function () {
    $this->postJson(route('local-worker-jobs.store'), [
        'url' => 'https://example.com/watch?v=dQw4w9WgXcQ',
        'start_seconds' => 30,
        'end_seconds' => 60,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('url');
});

test('local worker progress callback rejects invalid tokens', function () {
    $response = $this->postJson(route('local-worker-jobs.store'), [
        'url' => 'https://youtu.be/dQw4w9WgXcQ',
        'start_seconds' => 10,
        'end_seconds' => 40,
    ]);

    $jobUuid = $response->json('localWorkerJob.uuid');

    $this->patchJson(route('local-worker-jobs.update', ['localWorkerJob' => $jobUuid]), [
        'token' => str_repeat('x', 48),
        'status' => 'processing',
        'progress' => 50,
    ])->assertForbidden()
        ->assertJsonPath('message', 'Token local worker tidak valid.');
});

test('local worker progress callback updates processing and completed states', function () {
    $response = $this->postJson(route('local-worker-jobs.store'), [
        'url' => 'https://youtu.be/dQw4w9WgXcQ',
        'start_seconds' => 10,
        'end_seconds' => 40,
        'sync_output' => false,
    ]);

    $jobUuid = $response->json('localWorkerJob.uuid');
    $token = $response->json('localWorkerJob.manifest.callbacks.token');

    $this->patchJson(route('local-worker-jobs.update', ['localWorkerJob' => $jobUuid]), [
        'token' => $token,
        'status' => 'processing',
        'progress' => 42,
    ])->assertOk()
        ->assertJsonPath('localWorkerJob.status', 'processing')
        ->assertJsonPath('localWorkerJob.progress', 42)
        ->assertJsonPath('localWorkerJob.localOutputPath', null);

    $this->patchJson(route('local-worker-jobs.update', ['localWorkerJob' => $jobUuid]), [
        'token' => $token,
        'status' => 'completed',
        'progress' => 100,
        'local_output_path' => '/Users/local/Videos/freekliping-dQw4w9WgXcQ-10s-40s.mp4',
    ])->assertOk()
        ->assertJsonPath('localWorkerJob.status', 'completed')
        ->assertJsonPath('localWorkerJob.progress', 100)
        ->assertJsonPath('localWorkerJob.localOutputPath', '/Users/local/Videos/freekliping-dQw4w9WgXcQ-10s-40s.mp4');

    $job = LocalWorkerJob::query()->where('uuid', $jobUuid)->firstOrFail();

    expect($job->status)->toBe(LocalWorkerJobStatus::Completed)
        ->and($job->completed_at)->not->toBeNull();
});
