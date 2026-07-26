<?php

use App\Enums\ClipAspectRatio;
use App\Enums\ClipQuality;
use App\Enums\ClipStatus;
use App\Enums\SubtitleStatus;
use App\Jobs\ProcessClip;
use App\Models\Clip;
use App\Support\Clips\ClipProcessor;
use App\Support\Clips\SmartCropPlanner;
use App\Support\Clips\SubtitleBurner;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

test('home page exposes configured clip limits', function () {
    config([
        'freekliping.max_clip_length' => 180,
        'freekliping.support_url' => 'https://saweria.co/freekliping',
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->where('maxClipLength', 180)
            ->where('supportUrl', 'https://saweria.co/freekliping'));
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
            'subtitles' => [
                'en' => [
                    ['ext' => 'vtt', 'url' => 'https://example.com/manual.vtt'],
                ],
            ],
            'automatic_captions' => [
                'en' => [
                    ['ext' => 'vtt', 'url' => 'https://example.com/auto.vtt'],
                ],
            ],
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
        ->assertJsonPath('video.captions.available', true)
        ->assertJsonPath('video.captions.kind', 'manual')
        ->assertJsonPath('video.captions.language', 'en')
        ->assertJsonPath('limits.maxClipLength', 180)
        ->assertJsonPath('limits.retentionHours', 1);

    Process::assertRan(fn (PendingProcess $process, ProcessResult $result): bool => $process->timeout === 20
        && $process->command === [
            'yt-dlp',
            '--js-runtimes=node',
            '--dump-single-json',
            '--skip-download',
            '--no-warnings',
            '--no-playlist',
            'https://youtu.be/dQw4w9WgXcQ',
        ]);
});

test('metadata endpoint falls back to automatic captions when manual captions are unavailable', function () {
    config([
        'freekliping.subtitle_language' => 'id',
        'freekliping.yt_dlp_binary' => 'yt-dlp',
    ]);
    Process::preventStrayProcesses();
    Process::fake([
        '*' => Process::result(ytDlpMetadataOutput(
            duration: 123,
            automaticCaptions: [
                'id' => [
                    ['ext' => 'vtt', 'url' => 'https://example.com/auto.vtt'],
                ],
            ],
        )),
    ]);

    $this->postJson(route('clips.metadata'), [
        'url' => 'https://youtu.be/dQw4w9WgXcQ',
    ])->assertOk()
        ->assertJsonPath('video.captions.available', true)
        ->assertJsonPath('video.captions.kind', 'auto')
        ->assertJsonPath('video.captions.language', 'id');
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
        'rights_confirmed' => true,
        'subtitles_enabled' => true,
    ])->assertAccepted()
        ->assertJsonPath('clip.status', 'queued')
        ->assertJsonPath('clip.progress', 5)
        ->assertJsonPath('clip.aspectRatio', '9:16')
        ->assertJsonPath('clip.quality', '720p')
        ->assertJsonPath('clip.duration', 30)
        ->assertJsonPath('clip.subtitleStatus', null)
        ->assertJsonPath('clip.downloadUrl', null);

    $clip = Clip::query()->first();

    expect($clip)->not->toBeNull()
        ->and($clip->status)->toBe(ClipStatus::Queued)
        ->and($clip->start_seconds)->toBe(30)
        ->and($clip->end_seconds)->toBe(60)
        ->and($clip->aspect_ratio)->toBe(ClipAspectRatio::Vertical)
        ->and($clip->quality)->toBe(ClipQuality::P720)
        ->and($clip->subtitles_enabled)->toBeTrue();

    Queue::assertPushed(ProcessClip::class, fn (ProcessClip $job): bool => $job->clipId === $clip->id);
});

test('clip submit requires content rights confirmation before rendering', function () {
    Queue::fake();
    Process::preventStrayProcesses();
    Process::fake();

    $this->postJson(route('clips.store'), [
        'url' => 'https://youtu.be/dQw4w9WgXcQ',
        'start_seconds' => 10,
        'end_seconds' => 20,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('rights_confirmed');

    Queue::assertNothingPushed();
    Process::assertDidntRun('*');
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
        'rights_confirmed' => true,
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
        'rights_confirmed' => true,
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
        'rights_confirmed' => true,
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
        '--js-runtimes=node',
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
        '-c',
        'copy',
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
        '--js-runtimes=node',
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
        '-preset',
        'veryfast',
        '-crf',
        '23',
        '-c:a',
        'aac',
        '-movflags',
        '+faststart',
        "{$workDirectory}/output.mp4",
    ]);
});

test('smart crop planner falls back to center crop when detector is not configured', function () {
    Process::preventStrayProcesses();
    Process::fake();

    config([
        'freekliping.smart_crop.mode' => 'smart',
        'freekliping.smart_crop.detector_binary' => null,
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
        'status' => ClipStatus::Queued,
        'progress' => 5,
    ]);

    $filter = app(SmartCropPlanner::class)->filter($clip, '/tmp/source.mp4', 3, 30);

    expect($filter)->toBe('crop=min(iw\,ih*9/16):min(ih\,iw*16/9)');

    Process::assertDidntRun('*');
});

test('smart crop detector script is configured as the default detector binary', function () {
    $binary = config('freekliping.smart_crop.detector_binary');

    expect($binary)
        ->toBe(base_path('app/Support/Clips/smart_crop_detect.py'))
        ->and(is_executable($binary))->toBeTrue();
});

test('smart crop planner creates smoothed animated crop filters from detector points', function () {
    Process::preventStrayProcesses();
    Process::fake([
        '*' => Process::result(json_encode([
            'points' => [
                ['time' => 0, 'x' => 0.2, 'y' => 0.5, 'confidence' => 0.9],
                ['time' => 6, 'x' => 0.8, 'y' => 0.5, 'confidence' => 0.9],
            ],
        ], JSON_THROW_ON_ERROR)),
    ]);

    config([
        'freekliping.smart_crop.mode' => 'smart',
        'freekliping.smart_crop.detector_binary' => 'smart-crop-detect',
        'freekliping.smart_crop.detector_model' => '/models/yolo.onnx',
        'freekliping.smart_crop.smoothing' => 0.5,
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
        'status' => ClipStatus::Queued,
        'progress' => 5,
    ]);

    $sourceFile = storage_path("app/clip-processing/{$clip->uuid}/source.mp4");

    $filter = app(SmartCropPlanner::class)->filter($clip, $sourceFile, 3, 12);

    expect($filter)
        ->toStartWith('crop=min(iw\,ih*9/16):min(ih\,iw*16/9):')
        ->toContain('if(lt(t\,6.000)\,0.200+(0.500-0.200)*(t-0.000)/6.000')
        ->toContain('min(max(')
        ->toContain('\,iw-ow)')
        ->toContain('\,ih-oh)');

    Process::assertRan(fn (PendingProcess $process, ProcessResult $result): bool => $process->command === [
        'smart-crop-detect',
        '--model=/models/yolo.onnx',
        '--input',
        $sourceFile,
        '--start',
        '3',
        '--duration',
        '12',
        '--aspect-ratio',
        '9:16',
        '--output',
        storage_path("app/clip-processing/{$clip->uuid}/smart-crop.json"),
    ]);
});

test('smart crop benchmark command compares center and smart planning', function () {
    Process::preventStrayProcesses();
    Process::fake();

    $sourceFile = storage_path('app/clip-processing/benchmark-source.mp4');
    File::ensureDirectoryExists(dirname($sourceFile));
    File::put($sourceFile, 'source-video');

    $this->artisan('clips:smart-crop:benchmark', [
        'source' => $sourceFile,
        '--aspect-ratio' => '9:16',
        '--duration' => 12,
    ])->assertSuccessful();

    Process::assertDidntRun('*');

    File::delete($sourceFile);
});

test('processing job burns requested subtitles when a caption track is available', function () {
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
        'freekliping.subtitle_language' => 'en',
    ]);

    $clip = Clip::query()->create([
        'source_url' => 'https://youtu.be/dQw4w9WgXcQ',
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'title' => 'Example video',
        'channel' => 'Example channel',
        'duration_seconds' => 300,
        'start_seconds' => 30,
        'end_seconds' => 60,
        'subtitles_enabled' => true,
        'status' => ClipStatus::Queued,
        'progress' => 5,
    ]);

    $workDirectory = storage_path("app/clip-processing/{$clip->uuid}");
    File::ensureDirectoryExists($workDirectory);
    File::put("{$workDirectory}/source.mp4", 'source-video');
    File::put("{$workDirectory}/subtitle.en.srt", "1\n00:00:30,000 --> 00:00:32,000\nHello\n");
    File::put("{$workDirectory}/output.mp4", 'processed-video');

    (new ProcessClip($clip->id))->handle(app(ClipProcessor::class));

    $clip->refresh();

    expect($clip->subtitle_status)->toBe(SubtitleStatus::Burned);

    Process::assertRan(fn (PendingProcess $process, ProcessResult $result): bool => $process->command === [
        'yt-dlp',
        '--js-runtimes=node',
        '--write-subs',
        '--write-auto-subs',
        '--sub-langs',
        'en,id,id-orig',
        '--sub-format',
        'best',
        '--convert-subs',
        'srt',
        '--skip-download',
        '--no-playlist',
        '--no-warnings',
        '-o',
        "{$workDirectory}/subtitle",
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
        'subtitles='.addcslashes("{$workDirectory}/subtitle.styled.ass", '\\:'),
        '-c:v',
        'libx264',
        '-preset',
        'veryfast',
        '-crf',
        '23',
        '-c:a',
        'aac',
        '-movflags',
        '+faststart',
        "{$workDirectory}/output.mp4",
    ]);
});

test('subtitle burner writes styled ass tuned for vertical clips', function () {
    Process::preventStrayProcesses();
    Process::fake([
        '*' => Process::result(),
    ]);

    config(['freekliping.subtitle_language' => 'id']);

    $clip = Clip::query()->create([
        'source_url' => 'https://youtu.be/dQw4w9WgXcQ',
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'title' => 'Example video',
        'channel' => 'Example channel',
        'duration_seconds' => 300,
        'start_seconds' => 30,
        'end_seconds' => 60,
        'aspect_ratio' => ClipAspectRatio::Vertical,
        'subtitles_enabled' => true,
        'status' => ClipStatus::Queued,
        'progress' => 5,
    ]);

    $workDirectory = storage_path("app/clip-processing/{$clip->uuid}");
    File::ensureDirectoryExists($workDirectory);
    File::put("{$workDirectory}/subtitle.id.srt", "1\n00:00:30,000 --> 00:00:32,500\nHalo <i>dunia</i>, bro {tag}\n");

    $subtitlePath = app(SubtitleBurner::class)->prepare($clip, $workDirectory, 27);

    expect($subtitlePath)->toBe("{$workDirectory}/subtitle.styled.ass");

    $content = File::get($subtitlePath);

    expect($content)
        ->toContain('PlayResX: 1080')
        ->toContain('PlayResY: 1920')
        ->toContain('Style: FreeKlipingBase,DejaVu Sans,70,&H00FFFFFF,&H00FFFFFF,&H00000000,&H7A000000,-1,0,0,0,100,100,0,0,1,5,1,2,86,86,260,1')
        ->toContain('Dialogue: 0,0:00:03.00,0:00:05.50,FreeKlipingBase,,0000,0000,0000,,Halo dunia, bro tag');

    File::deleteDirectory($workDirectory);
});

test('subtitle burner ignores malformed srt cues without writing styled ass', function () {
    Process::preventStrayProcesses();
    Process::fake([
        '*' => Process::result(),
    ]);

    config(['freekliping.subtitle_language' => 'id']);

    $clip = Clip::query()->create([
        'source_url' => 'https://youtu.be/dQw4w9WgXcQ',
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'title' => 'Example video',
        'channel' => 'Example channel',
        'duration_seconds' => 300,
        'start_seconds' => 30,
        'end_seconds' => 60,
        'subtitles_enabled' => true,
        'status' => ClipStatus::Queued,
        'progress' => 5,
    ]);

    $workDirectory = storage_path("app/clip-processing/{$clip->uuid}");
    File::ensureDirectoryExists($workDirectory);
    File::put("{$workDirectory}/subtitle.id.srt", "1\nnot-a-time --> also-not-a-time\n\n2\n00:00:31,000 --> 00:00:32,000\n");

    expect(app(SubtitleBurner::class)->prepare($clip, $workDirectory, 27))->toBeNull();
    expect(File::exists("{$workDirectory}/subtitle.styled.ass"))->toBeFalse();

    File::deleteDirectory($workDirectory);
});

test('subtitle burner uses json3 word timing for active word highlights', function () {
    Process::preventStrayProcesses();
    Process::fake([
        '*' => Process::result(),
    ]);

    config(['freekliping.subtitle_language' => 'id']);

    $clip = Clip::query()->create([
        'source_url' => 'https://youtu.be/dQw4w9WgXcQ',
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'title' => 'Example video',
        'channel' => 'Example channel',
        'duration_seconds' => 300,
        'start_seconds' => 30,
        'end_seconds' => 60,
        'aspect_ratio' => ClipAspectRatio::Vertical,
        'subtitles_enabled' => true,
        'status' => ClipStatus::Queued,
        'progress' => 5,
    ]);

    $workDirectory = storage_path("app/clip-processing/{$clip->uuid}");
    File::ensureDirectoryExists($workDirectory);
    File::put("{$workDirectory}/subtitle.id.json3", json_encode([
        'events' => [
            [
                'tStartMs' => 30000,
                'dDurationMs' => 1300,
                'segs' => [
                    ['utf8' => 'Halo'],
                    ['utf8' => ' Halo', 'tOffsetMs' => 20],
                    ['utf8' => ' dunia', 'tOffsetMs' => 420],
                    ['utf8' => ' semua', 'tOffsetMs' => 860],
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR));

    $subtitlePath = app(SubtitleBurner::class)->prepare($clip, $workDirectory, 27);

    expect($subtitlePath)->toBe("{$workDirectory}/subtitle.styled.ass");

    $content = File::get($subtitlePath);

    expect($content)
        ->toContain('Dialogue: 0,0:00:02.92,0:00:04.54,FreeKlipingBase,,0000,0000,0000,,')
        ->toContain('{\c&H00FFFFFF&\t(80,81,\c&H005AE1FF&)\t(500,501,\c&H00FFFFFF&)}Halo')
        ->toContain('{\c&H00FFFFFF&\t(500,501,\c&H005AE1FF&)\t(940,941,\c&H00FFFFFF&)}dunia')
        ->toContain('{\c&H00FFFFFF&\t(940,941,\c&H005AE1FF&)\t(1460,1461,\c&H00FFFFFF&)}semua')
        ->not->toContain('Dialogue: 1');

    expect(substr_count($content, '}Halo'))->toBe(1);

    File::deleteDirectory($workDirectory);
});

test('subtitle burner keeps vertical word groups compact and non overlapping', function () {
    Process::preventStrayProcesses();
    Process::fake([
        '*' => Process::result(),
    ]);

    config(['freekliping.subtitle_language' => 'id']);

    $clip = Clip::query()->create([
        'source_url' => 'https://youtu.be/dQw4w9WgXcQ',
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'title' => 'Example video',
        'channel' => 'Example channel',
        'duration_seconds' => 300,
        'start_seconds' => 30,
        'end_seconds' => 60,
        'aspect_ratio' => ClipAspectRatio::Vertical,
        'subtitles_enabled' => true,
        'status' => ClipStatus::Queued,
        'progress' => 5,
    ]);

    $workDirectory = storage_path("app/clip-processing/{$clip->uuid}");
    File::ensureDirectoryExists($workDirectory);
    File::put("{$workDirectory}/subtitle.id.json3", json_encode([
        'events' => [
            [
                'tStartMs' => 30000,
                'dDurationMs' => 2500,
                'segs' => [
                    ['utf8' => 'ini,'],
                    ['utf8' => ' kalau', 'tOffsetMs' => 350],
                    ['utf8' => ' kamu', 'tOffsetMs' => 700],
                    ['utf8' => ' meneruskan', 'tOffsetMs' => 1050],
                    ['utf8' => ' ini.', 'tOffsetMs' => 1500],
                    ['utf8' => ' Jadi', 'tOffsetMs' => 1900],
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR));

    $subtitlePath = app(SubtitleBurner::class)->prepare($clip, $workDirectory, 27);
    $content = File::get($subtitlePath);

    expect($content)
        ->toContain('Dialogue: 0,0:00:02.92,0:00:04.05,FreeKlipingBase,,0000,0000,0000,,')
        ->toContain('Dialogue: 0,0:00:04.05,0:00:04.90,FreeKlipingBase,,0000,0000,0000,,')
        ->toContain('Dialogue: 0,0:00:04.90,0:00:05.58,FreeKlipingBase,,0000,0000,0000,,')
        ->not->toContain('\\N')
        ->not->toContain('Dialogue: 1');

    File::deleteDirectory($workDirectory);
});

test('processing job marks subtitles unavailable when no caption track is downloaded', function () {
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
        'freekliping.subtitle_language' => 'en',
    ]);

    $clip = Clip::query()->create([
        'source_url' => 'https://youtu.be/dQw4w9WgXcQ',
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'title' => 'Example video',
        'channel' => 'Example channel',
        'duration_seconds' => 300,
        'start_seconds' => 30,
        'end_seconds' => 60,
        'subtitles_enabled' => true,
        'status' => ClipStatus::Queued,
        'progress' => 5,
    ]);

    $workDirectory = storage_path("app/clip-processing/{$clip->uuid}");
    File::ensureDirectoryExists($workDirectory);
    File::put("{$workDirectory}/source.mp4", 'source-video');
    File::put("{$workDirectory}/output.mp4", 'processed-video');

    (new ProcessClip($clip->id))->handle(app(ClipProcessor::class));

    expect($clip->refresh()->subtitle_status)->toBe(SubtitleStatus::Unavailable);

    Process::assertRan(fn (PendingProcess $process, ProcessResult $result): bool => $process->command === [
        'yt-dlp',
        '--js-runtimes=node',
        '--write-subs',
        '--write-auto-subs',
        '--sub-langs',
        'en,id,id-orig',
        '--sub-format',
        'best',
        '--convert-subs',
        'srt',
        '--skip-download',
        '--no-playlist',
        '--no-warnings',
        '-o',
        "{$workDirectory}/subtitle",
        'https://youtu.be/dQw4w9WgXcQ',
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

function ytDlpMetadataOutput(int $duration = 123, array $subtitles = [], array $automaticCaptions = []): string
{
    return json_encode([
        'id' => 'dQw4w9WgXcQ',
        'title' => 'Example video',
        'channel' => 'Example channel',
        'duration' => $duration,
        'thumbnail' => 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
        'subtitles' => $subtitles,
        'automatic_captions' => $automaticCaptions,
    ], JSON_THROW_ON_ERROR);
}
