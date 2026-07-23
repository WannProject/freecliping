<?php

use App\Enums\ClipAnalysisStatus;
use App\Jobs\ProcessClipAnalysis;
use App\Models\ClipAnalysis;
use App\Support\Clips\ClipMomentRecommender;
use App\Support\Clips\YouTubeTranscriptClient;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;

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

    $this->postJson(route('clip-analyses.store'), [
        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'Video ini belum punya caption/transcript yang bisa dianalisis. Nanti bisa diproses lewat Whisper.');

    Queue::assertNothingPushed();
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

test('analysis job turns a json3 transcript into ranked recommendations', function () {
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

    Process::assertRan(fn (PendingProcess $process, ProcessResult $result): bool => $process->command === [
        'yt-dlp',
        '--write-subs',
        '--sub-langs',
        'id',
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
