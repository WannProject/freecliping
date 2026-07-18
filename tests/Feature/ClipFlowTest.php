<?php

use App\Enums\ClipAspectRatio;
use App\Enums\ClipQuality;
use App\Enums\ClipStatus;
use App\Jobs\ProcessClip;
use App\Models\Clip;
use App\Support\Clips\ClipProcessor;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

test('home page exposes configured clip limits', function () {
    config(['freekliping.max_clip_length' => 180]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->where('maxClipLength', 180));
});

test('metadata endpoint rejects invalid youtube urls before external calls', function () {
    Process::preventStrayProcesses();
    Process::fake();

    $this->postJson(route('clips.metadata'), [
        'url' => 'https://example.com/not-youtube',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('url');

    Process::assertDidntRun('*');
});

test('metadata endpoint requires yt dlp binary configuration', function () {
    config(['freekliping.yt_dlp_binary' => null]);
    Process::preventStrayProcesses();
    Process::fake();

    $this->postJson(route('clips.metadata'), [
        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ])->assertServiceUnavailable()
        ->assertJsonPath('message', 'Binary yt-dlp belum dikonfigurasi.');

    Process::assertDidntRun('*');
});

test('metadata endpoint returns youtube metadata from yt dlp', function () {
    config([
        'freekliping.metadata_timeout' => 20,
        'freekliping.yt_dlp_binary' => 'yt-dlp',
    ]);
    Process::preventStrayProcesses();
    Process::fake([
        '*' => Process::result(json_encode([
            'id' => 'dQw4w9WgXcQ',
            'title' => 'Example video',
            'channel' => 'Example channel',
            'duration' => 123,
            'thumbnail' => 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
        ], JSON_THROW_ON_ERROR)),
    ]);

    $this->postJson(route('clips.metadata'), [
        'url' => 'https://youtu.be/dQw4w9WgXcQ',
    ])->assertOk()
        ->assertJsonPath('video.id', 'dQw4w9WgXcQ')
        ->assertJsonPath('video.title', 'Example video')
        ->assertJsonPath('video.channel', 'Example channel')
        ->assertJsonPath('video.duration', 123)
        ->assertJsonPath('video.thumbnailUrl', 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg')
        ->assertJsonPath('limits.maxClipLength', 180)
        ->assertJsonPath('limits.retentionHours', 1);

    Process::assertRan(fn (PendingProcess $process, ProcessResult $result): bool => $process->timeout === 20
        && $process->command === [
            'yt-dlp',
            '--dump-single-json',
            '--skip-download',
            '--no-warnings',
            '--no-playlist',
            'https://youtu.be/dQw4w9WgXcQ',
        ]);
});

test('metadata endpoint rejects unavailable videos from yt dlp', function () {
    Process::preventStrayProcesses();
    Process::fake([
        '*' => Process::result(
            errorOutput: 'ERROR: Video unavailable',
            exitCode: 1,
        ),
    ]);

    $this->postJson(route('clips.metadata'), [
        'url' => 'https://www.youtube.com/shorts/dQw4w9WgXcQ',
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'Video tidak ditemukan atau tidak tersedia.');
});

test('metadata endpoint returns a clear network error from yt dlp', function () {
    Process::preventStrayProcesses();
    Process::fake([
        '*' => Process::result(
            errorOutput: "ERROR: [youtube] dQw4w9WgXcQ: Unable to download API page: Failed to resolve 'www.youtube.com' ([Errno -2] Name or service not known)",
            exitCode: 1,
        ),
    ]);

    $this->postJson(route('clips.metadata'), [
        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'Server tidak bisa menghubungi youtube.com. Cek DNS atau koneksi internet server.');
});

test('metadata endpoint returns a clear bot challenge error from yt dlp', function () {
    Process::preventStrayProcesses();
    Process::fake([
        '*' => Process::result(
            errorOutput: 'ERROR: [youtube] dQw4w9WgXcQ: Sign in to confirm you’re not a bot',
            exitCode: 1,
        ),
    ]);

    $this->postJson(route('clips.metadata'), [
        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'YouTube meminta verifikasi bot untuk video ini. Coba video lain atau update konfigurasi yt-dlp.');
});

test('metadata endpoint rejects private videos from yt dlp', function () {
    Process::preventStrayProcesses();
    Process::fake([
        '*' => Process::result(
            errorOutput: 'ERROR: This video is private',
            exitCode: 1,
        ),
    ]);

    $this->postJson(route('clips.metadata'), [
        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'Video tidak tersedia untuk diproses publik.');
});

test('clip submit stores a queued clip and dispatches processing job', function () {
    Queue::fake();
    Process::preventStrayProcesses();
    Process::fake([
        '*' => Process::result(ytDlpMetadataOutput(duration: 300)),
    ]);

    $this->postJson(route('clips.store'), [
        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'start_seconds' => 30,
        'end_seconds' => 60,
        'aspect_ratio' => '9:16',
        'quality' => '720p',
    ])->assertAccepted()
        ->assertJsonPath('clip.status', 'queued')
        ->assertJsonPath('clip.progress', 5)
        ->assertJsonPath('clip.aspectRatio', '9:16')
        ->assertJsonPath('clip.quality', '720p')
        ->assertJsonPath('clip.duration', 30)
        ->assertJsonPath('clip.downloadUrl', null);

    $clip = Clip::query()->first();

    expect($clip)->not->toBeNull()
        ->and($clip->status)->toBe(ClipStatus::Queued)
        ->and($clip->start_seconds)->toBe(30)
        ->and($clip->end_seconds)->toBe(60)
        ->and($clip->aspect_ratio)->toBe(ClipAspectRatio::Vertical)
        ->and($clip->quality)->toBe(ClipQuality::P720);

    Queue::assertPushed(ProcessClip::class, fn (ProcessClip $job): bool => $job->clipId === $clip->id);
});

test('clip submit rejects invalid export options', function () {
    Queue::fake();
    Process::preventStrayProcesses();
    Process::fake();

    $this->postJson(route('clips.store'), [
        'url' => 'https://youtu.be/dQw4w9WgXcQ',
        'start_seconds' => 10,
        'end_seconds' => 20,
        'aspect_ratio' => '3:2',
        'quality' => '8k',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['aspect_ratio', 'quality']);

    Queue::assertNothingPushed();
    Process::assertDidntRun('*');
});

test('clip submit rejects clips longer than the configured limit', function () {
    Queue::fake();
    Process::preventStrayProcesses();
    Process::fake();

    $this->postJson(route('clips.store'), [
        'url' => 'https://youtu.be/dQw4w9WgXcQ',
        'start_seconds' => 10,
        'end_seconds' => 220,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('end_seconds');

    Queue::assertNothingPushed();
    Process::assertDidntRun('*');
});

test('clip submit rejects end timestamps beyond video duration', function () {
    Queue::fake();
    Process::preventStrayProcesses();
    Process::fake([
        '*' => Process::result(ytDlpMetadataOutput(duration: 45)),
    ]);

    $this->postJson(route('clips.store'), [
        'url' => 'https://youtu.be/dQw4w9WgXcQ',
        'start_seconds' => 20,
        'end_seconds' => 60,
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'Titik akhir klip melewati durasi video.');

    Queue::assertNothingPushed();
});

test('processing job creates an mp4 output and marks clip completed', function () {
    Storage::fake('local');
    Process::preventStrayProcesses();
    Process::fake([
        '*' => Process::result(),
    ]);

    config([
        'freekliping.download_buffer_seconds' => 3,
        'freekliping.output_disk' => 'local',
        'freekliping.processing_timeout' => 600,
        'freekliping.retention_hours' => 1,
    ]);

    $clip = Clip::query()->create([
        'source_url' => 'https://youtu.be/dQw4w9WgXcQ',
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'title' => 'Example video',
        'channel' => 'Example channel',
        'duration_seconds' => 300,
        'start_seconds' => 30,
        'end_seconds' => 60,
        'status' => ClipStatus::Queued,
        'progress' => 5,
    ]);

    $workDirectory = storage_path("app/clip-processing/{$clip->uuid}");
    File::ensureDirectoryExists($workDirectory);
    File::put("{$workDirectory}/source.mp4", 'source-video');
    File::put("{$workDirectory}/output.mp4", 'processed-video');

    (new ProcessClip($clip->id))->handle(app(ClipProcessor::class));

    $clip->refresh();

    expect($clip->status)->toBe(ClipStatus::Completed)
        ->and($clip->progress)->toBe(100)
        ->and($clip->output_path)->toBe("clips/{$clip->uuid}.mp4")
        ->and($clip->output_size_bytes)->toBe(strlen('processed-video'))
        ->and($clip->downloadUrl())->toBeString();

    Storage::disk('local')->assertExists("clips/{$clip->uuid}.mp4");
    expect(File::exists($workDirectory))->toBeFalse();

    Process::assertRan(fn (PendingProcess $process, ProcessResult $result): bool => $process->command === [
        'yt-dlp',
        '--download-sections',
        '*00:00:27-00:01:03',
        '-f',
        'bv*+ba/b',
        '--merge-output-format',
        'mp4',
        '--no-playlist',
        '-o',
        "{$workDirectory}/source.%(ext)s",
        'https://youtu.be/dQw4w9WgXcQ',
    ]);

    Process::assertRan(fn (PendingProcess $process, ProcessResult $result): bool => $process->command === [
        'ffmpeg',
        '-y',
        '-i',
        "{$workDirectory}/source.mp4",
        '-ss',
        '3',
        '-t',
        '30',
        '-c:v',
        'libx264',
        '-c:a',
        'aac',
        '-movflags',
        '+faststart',
        "{$workDirectory}/output.mp4",
    ]);
});

test('processing job applies selected aspect ratio and quality', function () {
    Storage::fake('local');
    Process::preventStrayProcesses();
    Process::fake([
        '*' => Process::result(),
    ]);

    config([
        'freekliping.download_buffer_seconds' => 3,
        'freekliping.output_disk' => 'local',
        'freekliping.processing_timeout' => 600,
        'freekliping.retention_hours' => 1,
    ]);

    $clip = Clip::query()->create([
        'source_url' => 'https://youtu.be/dQw4w9WgXcQ',
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'title' => 'Example video',
        'channel' => 'Example channel',
        'duration_seconds' => 300,
        'start_seconds' => 30,
        'end_seconds' => 60,
        'aspect_ratio' => ClipAspectRatio::Vertical,
        'quality' => ClipQuality::P720,
        'status' => ClipStatus::Queued,
        'progress' => 5,
    ]);

    $workDirectory = storage_path("app/clip-processing/{$clip->uuid}");
    File::ensureDirectoryExists($workDirectory);
    File::put("{$workDirectory}/source.mp4", 'source-video');
    File::put("{$workDirectory}/output.mp4", 'processed-video');

    (new ProcessClip($clip->id))->handle(app(ClipProcessor::class));

    Process::assertRan(fn (PendingProcess $process, ProcessResult $result): bool => $process->command === [
        'yt-dlp',
        '--download-sections',
        '*00:00:27-00:01:03',
        '-f',
        'bv*[height<=720]+ba/b[height<=720]/b',
        '--merge-output-format',
        'mp4',
        '--no-playlist',
        '-o',
        "{$workDirectory}/source.%(ext)s",
        'https://youtu.be/dQw4w9WgXcQ',
    ]);

    Process::assertRan(fn (PendingProcess $process, ProcessResult $result): bool => $process->command === [
        'ffmpeg',
        '-y',
        '-i',
        "{$workDirectory}/source.mp4",
        '-ss',
        '3',
        '-t',
        '30',
        '-vf',
        'crop=min(iw\,ih*9/16):min(ih\,iw*16/9),scale=720:1280',
        '-c:v',
        'libx264',
        '-c:a',
        'aac',
        '-movflags',
        '+faststart',
        "{$workDirectory}/output.mp4",
    ]);
});

test('completed clips can be downloaded with a signed url', function () {
    Storage::fake('local');

    $clip = Clip::query()->create([
        'source_url' => 'https://youtu.be/dQw4w9WgXcQ',
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'title' => 'Example video',
        'channel' => 'Example channel',
        'duration_seconds' => 300,
        'start_seconds' => 30,
        'end_seconds' => 60,
        'status' => ClipStatus::Completed,
        'progress' => 100,
        'output_disk' => 'local',
        'output_path' => 'clips/test.mp4',
        'output_size_bytes' => 9,
        'output_expires_at' => now()->addHour(),
    ]);

    Storage::disk('local')->put('clips/test.mp4', 'clip-file');

    $this->get(URL::temporarySignedRoute('clips.download', now()->addHour(), ['clip' => $clip]))
        ->assertOk();
});

test('completed clip filename can be updated', function () {
    $clip = Clip::query()->create([
        'source_url' => 'https://youtu.be/dQw4w9WgXcQ',
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'title' => 'Example video',
        'channel' => 'Example channel',
        'duration_seconds' => 300,
        'start_seconds' => 30,
        'end_seconds' => 60,
        'status' => ClipStatus::Completed,
        'progress' => 100,
        'output_disk' => 'local',
        'output_path' => 'clips/test.mp4',
        'output_size_bytes' => 9,
        'output_expires_at' => now()->addHour(),
    ]);

    $this->patchJson(route('clips.filename.update', $clip), [
        'file_name' => 'Launch Reel.mp4',
    ])->assertOk()
        ->assertJsonPath('clip.fileName', 'Launch Reel.mp4');

    expect($clip->refresh()->custom_file_name)->toBe('Launch Reel');
});

test('queued clip filename cannot be updated', function () {
    $clip = Clip::query()->create([
        'source_url' => 'https://youtu.be/dQw4w9WgXcQ',
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'title' => 'Example video',
        'channel' => 'Example channel',
        'duration_seconds' => 300,
        'start_seconds' => 30,
        'end_seconds' => 60,
        'status' => ClipStatus::Queued,
        'progress' => 5,
    ]);

    $this->patchJson(route('clips.filename.update', $clip), [
        'file_name' => 'Launch Reel',
    ])->assertConflict()
        ->assertJsonPath('message', 'Nama file hanya bisa diubah setelah klip selesai diproses.');
});

function ytDlpMetadataOutput(int $duration = 123): string
{
    return json_encode([
        'id' => 'dQw4w9WgXcQ',
        'title' => 'Example video',
        'channel' => 'Example channel',
        'duration' => $duration,
        'thumbnail' => 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
    ], JSON_THROW_ON_ERROR);
}
