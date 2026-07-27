<?php

use App\Enums\ClipAnalysisStatus;
use App\Jobs\ProcessClipAnalysis;
use App\Models\ClipAnalysis;
use App\Support\Clips\ClipMomentRecommender;
use App\Support\Clips\WhisperTranscriber;
use App\Support\Clips\YouTubeTranscriptClient;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

test('analysis endpoint stores a queued analysis and dispatches the job', function () {
    Queue::fake();
    Process::preventStrayProcesses();
    Process::fake([
        '*' => Process::result(analysisMetadataOutput()),
    ]);

    $this->postJson(route('clip-analyses.store'), [
        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ])->assertAccepted()
        ->assertJsonPath('analysis.status', 'queued')
        ->assertJsonPath('analysis.progress', 5)
        ->assertJsonPath('analysis.video.id', 'dQw4w9WgXcQ')
        ->assertJsonPath('analysis.video.captions.available', true)
        ->assertJsonCount(0, 'analysis.recommendations');

    $analysis = ClipAnalysis::query()->first();

    expect($analysis)->not->toBeNull()
        ->and($analysis->status)->toBe(ClipAnalysisStatus::Queued)
        ->and($analysis->youtube_video_id)->toBe('dQw4w9WgXcQ')
        ->and($analysis->requested_ip)->toBe('127.0.0.1');

    Queue::assertPushed(ProcessClipAnalysis::class, fn (ProcessClipAnalysis $job): bool => $job->analysisId === $analysis->id);
});

test('analysis endpoint rejects videos without transcript tracks', function () {
    Queue::fake();
    Process::preventStrayProcesses();
    Process::fake([
        '*' => Process::result(analysisMetadataOutput(automaticCaptions: [])),
    ]);
    config(['freekliping.whisper.enabled' => false]);

    $this->postJson(route('clip-analyses.store'), [
        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'Video ini belum punya caption/transcript yang bisa dianalisis. Aktifkan Whisper untuk fallback transcription.');

    Queue::assertNothingPushed();
});

test('analysis endpoint queues videos without transcript tracks when whisper is enabled', function () {
    Queue::fake();
    Process::preventStrayProcesses();
    Process::fake([
        '*' => Process::result(analysisMetadataOutput(automaticCaptions: [])),
    ]);

    config(['freekliping.whisper.enabled' => true]);

    $this->postJson(route('clip-analyses.store'), [
        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ])->assertAccepted()
        ->assertJsonPath('analysis.status', 'queued');

    Queue::assertPushed(ProcessClipAnalysis::class);
});

test('analysis endpoint rejects videos that exceed analysis length limit', function () {
    Queue::fake();
    Process::preventStrayProcesses();
    Process::fake([
        '*' => Process::result(analysisMetadataOutput(duration: 7201)),
    ]);

    config(['freekliping.max_analysis_video_length' => 7200]);

    $this->postJson(route('clip-analyses.store'), [
        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'Video terlalu panjang untuk dianalisis pada MVP.');

    Queue::assertNothingPushed();
});

test('analysis endpoint reuses a recent completed analysis for the same video', function () {
    Queue::fake();
    Process::preventStrayProcesses();
    Process::fake([
        '*' => Process::result(analysisMetadataOutput()),
    ]);

    ClipAnalysis::query()->create([
        'source_url' => 'https://youtu.be/dQw4w9WgXcQ',
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'title' => 'Cached video',
        'channel' => 'Cached channel',
        'duration_seconds' => 120,
        'status' => ClipAnalysisStatus::Completed,
        'progress' => 100,
        'recommendations' => [
            [
                'id' => 'cached-1',
                'startSeconds' => 30,
                'endSeconds' => 58,
                'duration' => 28,
                'score' => 91,
                'title' => 'Cached recommendation',
                'hook' => 'Cached hook',
                'category' => 'Argumen kuat',
                'emotion' => 'penasaran',
                'reason' => 'Cached reason.',
                'openingText' => 'Cached hook',
                'caption' => 'Cached caption',
                'transcriptExcerpt' => 'Cached transcript',
            ],
        ],
        'completed_at' => now(),
    ]);

    $this->postJson(route('clip-analyses.store'), [
        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ])->assertOk()
        ->assertJsonPath('analysis.status', 'completed')
        ->assertJsonPath('analysis.recommendations.0.id', 'cached-1');

    Queue::assertNothingPushed();
});

test('analysis endpoint returns the active analysis when the requester still has one running', function () {
    Queue::fake();
    Process::preventStrayProcesses();
    Process::fake([
        '*' => Process::result(analysisMetadataOutput()),
    ]);

    config(['freekliping.max_pending_analyses_per_ip' => 1]);

    $analysis = ClipAnalysis::query()->create([
        'source_url' => 'https://youtu.be/dQw4w9WgXcQ',
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'title' => 'Active video',
        'channel' => 'Active channel',
        'duration_seconds' => 120,
        'status' => ClipAnalysisStatus::Processing,
        'progress' => 35,
        'requested_ip' => '127.0.0.1',
    ]);

    $this->postJson(route('clip-analyses.store'), [
        'url' => 'https://www.youtube.com/watch?v=ysz5S6PUM-U',
    ])->assertTooManyRequests()
        ->assertJsonPath('analysis.uuid', $analysis->uuid)
        ->assertJsonPath('analysis.status', 'processing')
        ->assertJsonPath('analysis.cancelUrl', route('clip-analyses.cancel', $analysis));

    Queue::assertNothingPushed();
});

test('analysis endpoint cancels a pending analysis for the same requester', function () {
    Queue::fake();

    config(['freekliping.max_pending_analyses_per_ip' => 1]);

    $analysis = ClipAnalysis::query()->create([
        'source_url' => 'https://youtu.be/dQw4w9WgXcQ',
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'title' => 'Active video',
        'channel' => 'Active channel',
        'duration_seconds' => 120,
        'status' => ClipAnalysisStatus::Processing,
        'progress' => 35,
        'requested_ip' => '127.0.0.1',
    ]);

    $this->patchJson(route('clip-analyses.cancel', $analysis))
        ->assertOk()
        ->assertJsonPath('analysis.status', 'cancelled')
        ->assertJsonPath('analysis.errorMessage', 'Analisis video dibatalkan.');

    $analysis->refresh();

    expect($analysis->status)->toBe(ClipAnalysisStatus::Cancelled)
        ->and($analysis->progress)->toBe(100);

    Process::preventStrayProcesses();
    Process::fake([
        '*' => Process::result(analysisMetadataOutput()),
    ]);

    $this->postJson(route('clip-analyses.store'), [
        'url' => 'https://www.youtube.com/watch?v=ysz5S6PUM-U',
    ])->assertAccepted();

    Queue::assertPushed(ProcessClipAnalysis::class);
});

test('analysis endpoint rejects cancelling another requester analysis', function () {
    $analysis = ClipAnalysis::query()->create([
        'source_url' => 'https://youtu.be/dQw4w9WgXcQ',
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'title' => 'Active video',
        'channel' => 'Active channel',
        'duration_seconds' => 120,
        'status' => ClipAnalysisStatus::Processing,
        'progress' => 35,
        'requested_ip' => '10.10.10.10',
    ]);

    $this->patchJson(route('clip-analyses.cancel', $analysis))
        ->assertForbidden()
        ->assertJsonPath('message', 'Analisis ini tidak bisa dibatalkan dari sesi ini.');

    expect($analysis->refresh()->status)->toBe(ClipAnalysisStatus::Processing);
});

test('analysis job turns a json3 transcript into ranked recommendations', function () {
    Storage::fake('local');
    Process::preventStrayProcesses();
    Process::fake([
        '*' => Process::result(),
    ]);

    $analysis = ClipAnalysis::query()->create([
        'source_url' => 'https://youtu.be/dQw4w9WgXcQ',
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'title' => 'Example video',
        'channel' => 'Example channel',
        'duration_seconds' => 120,
        'status' => ClipAnalysisStatus::Queued,
        'progress' => 5,
    ]);

    $workDirectory = storage_path("app/clip-analysis/{$analysis->uuid}");
    File::ensureDirectoryExists($workDirectory);
    File::put("{$workDirectory}/transcript.id.json3", analysisJson3Transcript());

    (new ProcessClipAnalysis($analysis->id))->handle(
        app(YouTubeTranscriptClient::class),
        app(WhisperTranscriber::class),
        app(ClipMomentRecommender::class),
    );

    $analysis->refresh();

    expect($analysis->status)->toBe(ClipAnalysisStatus::Completed)
        ->and($analysis->progress)->toBe(100)
        ->and($analysis->transcript_language)->toBe('id')
        ->and($analysis->recommendations)->toHaveCount(3)
        ->and($analysis->recommendations[0]['score'])->toBeGreaterThan(70)
        ->and($analysis->recommendations[0]['hook'])->toContain('korupsi');

    expect(File::exists($workDirectory))->toBeFalse();
    expect(Storage::disk('local')->exists('clip-analysis/transcripts/youtube/dQw4w9WgXcQ-id.json3'))->toBeTrue();

    Process::assertRan(fn (PendingProcess $process, ProcessResult $result): bool => $process->command === [
        'yt-dlp',
        '--js-runtimes=node',
        '--write-subs',
        '--write-auto-subs',
        '--sub-langs',
        'id,id-orig,en',
        '--sub-format',
        'json3',
        '--skip-download',
        '--no-playlist',
        '--no-warnings',
        '-o',
        "{$workDirectory}/transcript",
        'https://youtu.be/dQw4w9WgXcQ',
    ]);
});

test('analysis job reuses cached youtube transcript without running yt-dlp', function () {
    Storage::fake('local');
    Process::preventStrayProcesses();
    Process::fake([
        '*' => Process::result(),
    ]);

    $analysis = ClipAnalysis::query()->create([
        'source_url' => 'https://youtu.be/dQw4w9WgXcQ',
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'title' => 'Example video',
        'channel' => 'Example channel',
        'duration_seconds' => 120,
        'status' => ClipAnalysisStatus::Queued,
        'progress' => 5,
    ]);

    Storage::disk('local')->put('clip-analysis/transcripts/youtube/dQw4w9WgXcQ-id.json3', analysisJson3Transcript());

    (new ProcessClipAnalysis($analysis->id))->handle(
        app(YouTubeTranscriptClient::class),
        app(WhisperTranscriber::class),
        app(ClipMomentRecommender::class),
    );

    $analysis->refresh();

    expect($analysis->status)->toBe(ClipAnalysisStatus::Completed)
        ->and($analysis->transcript_language)->toBe('id')
        ->and($analysis->recommendations)->toHaveCount(3);

    Process::assertNothingRan();
});

test('analysis job does not process a cancelled analysis', function () {
    Process::preventStrayProcesses();
    Process::fake([
        '*' => Process::result(),
    ]);

    $analysis = ClipAnalysis::query()->create([
        'source_url' => 'https://youtu.be/dQw4w9WgXcQ',
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'title' => 'Example video',
        'channel' => 'Example channel',
        'duration_seconds' => 120,
        'status' => ClipAnalysisStatus::Cancelled,
        'progress' => 100,
        'error_message' => 'Analisis video dibatalkan.',
    ]);

    (new ProcessClipAnalysis($analysis->id))->handle(
        app(YouTubeTranscriptClient::class),
        app(WhisperTranscriber::class),
        app(ClipMomentRecommender::class),
    );

    $analysis->refresh();

    expect($analysis->status)->toBe(ClipAnalysisStatus::Cancelled)
        ->and($analysis->error_message)->toBe('Analisis video dibatalkan.');

    Process::assertNothingRan();
});

test('analysis job falls back to cached whisper transcript when youtube transcript is unavailable', function () {
    Storage::fake('local');
    Process::preventStrayProcesses();
    Process::fake([
        '*' => Process::result(),
    ]);

    config([
        'freekliping.whisper.enabled' => true,
        'freekliping.whisper.model' => 'small',
    ]);

    $analysis = ClipAnalysis::query()->create([
        'source_url' => 'https://youtu.be/dQw4w9WgXcQ',
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'title' => 'Example video',
        'channel' => 'Example channel',
        'duration_seconds' => 120,
        'status' => ClipAnalysisStatus::Queued,
        'progress' => 5,
    ]);

    Storage::disk('local')->put('clip-analysis/transcripts/dQw4w9WgXcQ-small-id.json3', analysisJson3Transcript());

    (new ProcessClipAnalysis($analysis->id))->handle(
        app(YouTubeTranscriptClient::class),
        app(WhisperTranscriber::class),
        app(ClipMomentRecommender::class),
    );

    $analysis->refresh();

    expect($analysis->status)->toBe(ClipAnalysisStatus::Completed)
        ->and($analysis->transcript_language)->toBe('id')
        ->and($analysis->recommendations)->toHaveCount(3);

    Process::assertRan(fn (PendingProcess $process, ProcessResult $result): bool => $process->command === [
        'yt-dlp',
        '--js-runtimes=node',
        '--write-subs',
        '--write-auto-subs',
        '--sub-langs',
        'id,id-orig,en',
        '--sub-format',
        'json3',
        '--skip-download',
        '--no-playlist',
        '--no-warnings',
        '-o',
        storage_path("app/clip-analysis/{$analysis->uuid}/transcript"),
        'https://youtu.be/dQw4w9WgXcQ',
    ]);
});

test('whisper transcriber downloads audio, runs faster whisper, and caches json3 transcript', function () {
    Storage::fake('local');
    Process::preventStrayProcesses();
    Process::fake([
        '*' => Process::result(),
    ]);

    config([
        'freekliping.whisper.enabled' => true,
        'freekliping.whisper.model' => 'base',
        'freekliping.whisper.binary' => 'whisper-transcribe',
        'freekliping.whisper.timeout' => 900,
    ]);

    $analysis = ClipAnalysis::query()->create([
        'source_url' => 'https://youtu.be/dQw4w9WgXcQ',
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'title' => 'Example video',
        'channel' => 'Example channel',
        'duration_seconds' => 120,
        'status' => ClipAnalysisStatus::Queued,
        'progress' => 5,
    ]);
    $workDirectory = storage_path("app/clip-analysis/{$analysis->uuid}");
    File::ensureDirectoryExists($workDirectory);
    File::put("{$workDirectory}/whisper.json", json_encode(['language' => 'id'], JSON_THROW_ON_ERROR));
    File::put("{$workDirectory}/whisper.json3", analysisJson3Transcript());

    $transcript = app(WhisperTranscriber::class)->transcribe($analysis, $workDirectory);

    expect($transcript['language'])->toBe('id')
        ->and($transcript['content'])->toBe(analysisJson3Transcript())
        ->and(Storage::disk('local')->exists('clip-analysis/transcripts/dQw4w9WgXcQ-base-id.json3'))->toBeTrue();

    Process::assertRan(fn (PendingProcess $process, ProcessResult $result): bool => $process->command === [
        'yt-dlp',
        '--js-runtimes=node',
        '-f',
        'ba/b',
        '--extract-audio',
        '--audio-format',
        'm4a',
        '--no-playlist',
        '--no-warnings',
        '-o',
        "{$workDirectory}/whisper-audio.m4a",
        'https://youtu.be/dQw4w9WgXcQ',
    ]);

    Process::assertRan(fn (PendingProcess $process, ProcessResult $result): bool => $process->command === [
        'whisper-transcribe',
        '--input',
        "{$workDirectory}/whisper-audio.m4a",
        '--output-json',
        "{$workDirectory}/whisper.json",
        '--output-json3',
        "{$workDirectory}/whisper.json3",
        '--output-srt',
        "{$workDirectory}/whisper.srt",
        '--model',
        'base',
        '--language',
        'id',
        '--device',
        'cpu',
        '--compute-type',
        'int8',
        '--beam-size',
        '5',
    ]);

    File::deleteDirectory($workDirectory);
});

test('whisper benchmark command runs the configured transcriber script', function () {
    Process::preventStrayProcesses();
    Process::fake([
        '*' => Process::result(),
    ]);

    config([
        'freekliping.whisper.binary' => 'whisper-transcribe',
        'freekliping.whisper.model' => 'base',
    ]);

    $input = storage_path('app/clip-analysis/benchmark-audio.m4a');
    File::ensureDirectoryExists(dirname($input));
    File::put($input, 'audio');

    $this->artisan('clips:whisper:benchmark', [
        'input' => $input,
        '--language' => 'id',
    ])->assertSuccessful();

    Process::assertRan(fn (PendingProcess $process, ProcessResult $result): bool => $process->command[0] === 'whisper-transcribe'
        && in_array('--output-json3', $process->command, true)
        && in_array('base', $process->command, true));

    File::delete($input);
});

test('moment recommender skips heavily overlapping candidate windows', function () {
    $content = json_encode([
        'events' => collect(range(0, 9))
            ->map(fn (int $index): array => analysisEvent(
                $index * 4000,
                4000,
                'Kalau sistem korupsi terus dibiarkan, rakyat marah dan solusi transparan harus dibuka sekarang.',
            ))
            ->all(),
    ], JSON_THROW_ON_ERROR);

    $recommendations = app(ClipMomentRecommender::class)->recommendFromJson3($content, 45);

    expect($recommendations)->not->toBeEmpty();

    foreach ($recommendations as $index => $recommendation) {
        foreach (array_slice($recommendations, $index + 1) as $otherRecommendation) {
            $overlapStart = max($recommendation['startSeconds'], $otherRecommendation['startSeconds']);
            $overlapEnd = min($recommendation['endSeconds'], $otherRecommendation['endSeconds']);
            $overlapSeconds = max(0, $overlapEnd - $overlapStart);
            $shortestDuration = min(
                $recommendation['endSeconds'] - $recommendation['startSeconds'],
                $otherRecommendation['endSeconds'] - $otherRecommendation['startSeconds'],
            );

            expect($overlapSeconds / $shortestDuration)->toBeLessThanOrEqual(0.55);
        }
    }
});

test('analysis status endpoint returns recommendations', function () {
    $analysis = ClipAnalysis::query()->create([
        'source_url' => 'https://youtu.be/dQw4w9WgXcQ',
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'title' => 'Example video',
        'channel' => 'Example channel',
        'duration_seconds' => 120,
        'status' => ClipAnalysisStatus::Completed,
        'progress' => 100,
        'recommendations' => [
            [
                'id' => 'recommendation-1',
                'startSeconds' => 30,
                'endSeconds' => 58,
                'duration' => 28,
                'score' => 91,
                'title' => 'Korupsi dan sistem',
                'hook' => 'Kalau kita mau hilangkan korupsi',
                'category' => 'Pernyataan kontroversial',
                'emotion' => 'ingin berdebat',
                'reason' => 'Punya hook cepat.',
                'openingText' => 'Kalau kita mau hilangkan korupsi',
                'caption' => 'Setuju?',
                'transcriptExcerpt' => 'Kalau kita mau hilangkan korupsi...',
            ],
        ],
    ]);

    $this->getJson(route('clip-analyses.show', $analysis))
        ->assertOk()
        ->assertJsonPath('analysis.uuid', $analysis->uuid)
        ->assertJsonPath('analysis.recommendations.0.score', 91)
        ->assertJsonPath('analysis.statusUrl', route('clip-analyses.show', $analysis));
});

function analysisMetadataOutput(int $duration = 120, array $automaticCaptions = [
    'id' => [
        ['ext' => 'json3', 'url' => 'https://example.com/auto.json3'],
    ],
]): string
{
    return json_encode([
        'id' => 'dQw4w9WgXcQ',
        'title' => 'Example video',
        'channel' => 'Example channel',
        'duration' => $duration,
        'thumbnail' => 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
        'subtitles' => [],
        'automatic_captions' => $automaticCaptions,
    ], JSON_THROW_ON_ERROR);
}

function analysisJson3Transcript(): string
{
    return json_encode([
        'events' => [
            analysisEvent(30000, 6000, 'Kalau kita mau hilangkan korupsi, jangan cuma ganti orang.'),
            analysisEvent(36000, 6000, 'Masalahnya sistem pejabat itu saling menjaga dan rakyat akhirnya marah.'),
            analysisEvent(42000, 7000, 'Harus ada cara baru yang lebih transparan supaya negara tidak diakali.'),
            analysisEvent(49000, 7000, 'Ini bukan sekadar debat politik, ini soal masa depan Indonesia.'),
            analysisEvent(56000, 7000, 'Kalau aturan tidak diubah, presiden siapa pun menghadapi masalah yang sama.'),
            analysisEvent(70000, 6000, 'Kadang orang ketawa, tapi ini sebenarnya persoalan serius.'),
            analysisEvent(76000, 7000, 'Solusinya harus dimulai dari sistem yang terbuka dan bisa diawasi.'),
            analysisEvent(83000, 7000, 'Kalau rakyat tidak bisa melihat prosesnya, kepercayaan akan hancur.'),
        ],
    ], JSON_THROW_ON_ERROR);
}

/**
 * @return array{tStartMs: int, dDurationMs: int, segs: array<int, array{utf8: string}>}
 */
function analysisEvent(int $startMs, int $durationMs, string $text): array
{
    return [
        'tStartMs' => $startMs,
        'dDurationMs' => $durationMs,
        'segs' => [
            ['utf8' => $text],
        ],
    ];
}
