<?php

use App\Enums\ClipAnalysisStatus;
use App\Enums\ClipStatus;
use App\Models\Clip;
use App\Models\ClipAnalysis;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test('prune deletes expired output files and their records', function () {
    config([
        'freekliping.retention_hours' => 1,
        'freekliping.prune_after_hours' => 0,
    ]);

    $expired = Clip::create([
        'source_url' => 'https://youtu.be/dQw4w9WgXcQ',
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'start_seconds' => 0,
        'end_seconds' => 30,
        'status' => ClipStatus::Completed,
        'output_disk' => 'local',
        'output_path' => 'clips/expired.mp4',
        'output_expires_at' => now()->subHours(2),
        'output_size_bytes' => 1024,
    ]);
    Storage::disk('local')->put('clips/expired.mp4', 'fake');

    Artisan::call('clips:prune');

    Storage::disk('local')->assertMissing('clips/expired.mp4');
    expect(Clip::whereKey($expired->id)->exists())->toBeFalse();
});

test('prune keeps clips whose output is still within retention', function () {
    config(['freekliping.prune_after_hours' => 24]);

    $active = Clip::create([
        'source_url' => 'https://youtu.be/dQw4w9WgXcQ',
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'start_seconds' => 0,
        'end_seconds' => 30,
        'status' => ClipStatus::Completed,
        'output_disk' => 'local',
        'output_path' => 'clips/active.mp4',
        'output_expires_at' => now()->addHour(),
        'output_size_bytes' => 1024,
    ]);
    Storage::disk('local')->put('clips/active.mp4', 'fake');

    Artisan::call('clips:prune');

    Storage::disk('local')->assertExists('clips/active.mp4');
    expect(Clip::whereKey($active->id)->exists())->toBeTrue();
});

test('prune removes old failed records', function () {
    config(['freekliping.prune_after_hours' => 1]);

    $failed = Clip::create([
        'source_url' => 'https://youtu.be/dQw4w9WgXcQ',
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'start_seconds' => 0,
        'end_seconds' => 30,
        'status' => ClipStatus::Failed,
        'error_message' => 'boom',
    ]);
    $failed->forceFill([
        'created_at' => now()->subHours(3),
        'updated_at' => now()->subHours(3),
    ])->save();

    Artisan::call('clips:prune');

    expect(Clip::whereKey($failed->id)->exists())->toBeFalse();
});

test('prune removes stale completed analysis records only', function () {
    config(['freekliping.analysis_retention_hours' => 24]);

    $staleCompleted = ClipAnalysis::create([
        'source_url' => 'https://youtu.be/dQw4w9WgXcQ',
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'title' => 'Old completed analysis',
        'channel' => 'Example channel',
        'duration_seconds' => 300,
        'status' => ClipAnalysisStatus::Completed,
        'progress' => 100,
        'recommendations' => [],
    ]);
    $staleCompleted->forceFill([
        'created_at' => now()->subDays(2),
        'updated_at' => now()->subDays(2),
    ])->save();

    $recentCompleted = ClipAnalysis::create([
        'source_url' => 'https://youtu.be/dQw4w9WgXcQ',
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'title' => 'Recent completed analysis',
        'channel' => 'Example channel',
        'duration_seconds' => 300,
        'status' => ClipAnalysisStatus::Completed,
        'progress' => 100,
        'recommendations' => [],
    ]);

    $staleQueued = ClipAnalysis::create([
        'source_url' => 'https://youtu.be/dQw4w9WgXcQ',
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'title' => 'Queued analysis',
        'channel' => 'Example channel',
        'duration_seconds' => 300,
        'status' => ClipAnalysisStatus::Queued,
        'progress' => 5,
        'recommendations' => [],
    ]);
    $staleQueued->forceFill([
        'created_at' => now()->subDays(2),
        'updated_at' => now()->subDays(2),
    ])->save();

    Artisan::call('clips:prune');

    expect(ClipAnalysis::whereKey($staleCompleted->id)->exists())->toBeFalse()
        ->and(ClipAnalysis::whereKey($recentCompleted->id)->exists())->toBeTrue()
        ->and(ClipAnalysis::whereKey($staleQueued->id)->exists())->toBeTrue();
});
