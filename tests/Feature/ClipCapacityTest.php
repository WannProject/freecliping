<?php

use App\Enums\ClipStatus;
use App\Jobs\ProcessClip;
use App\Models\Clip;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    Process::preventStrayProcesses();
    Process::fake([
        '*' => Process::result(json_encode([
            'id' => 'dQw4w9WgXcQ',
            'title' => 'Example video',
            'channel' => 'Example channel',
            'duration' => 300,
            'thumbnail' => 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
        ], JSON_THROW_ON_ERROR)),
    ]);
});

test('store rejects a clip when the requesting IP has too many in flight', function () {
    config(['freekliping.max_pending_per_ip' => 1]);

    Clip::create([
        'source_url' => 'https://youtu.be/dQw4w9WgXcQ',
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'start_seconds' => 0,
        'end_seconds' => 30,
        'status' => ClipStatus::Queued,
        'requested_ip' => '127.0.0.1',
    ]);

    $this->postJson(route('clips.store'), [
        'url' => 'https://youtu.be/dQw4w9WgXcQ',
        'start_seconds' => 10,
        'end_seconds' => 20,
        'rights_confirmed' => true,
    ])->assertTooManyRequests()
        ->assertJsonPath('message', 'Kamu masih punya klip yang sedang diproses. Tunggu sampai selesai sebelum membuat klip baru.');

    Queue::assertNothingPushed();
    Process::assertDidntRun('*');
});

test('store rejects a clip when the global queue is overloaded', function () {
    config([
        'freekliping.max_pending_per_ip' => 5,
        'freekliping.max_concurrent_clips' => 1,
    ]);

    Clip::create([
        'source_url' => 'https://youtu.be/dQw4w9WgXcQ',
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'start_seconds' => 0,
        'end_seconds' => 30,
        'status' => ClipStatus::Processing,
        'requested_ip' => '127.0.0.1',
    ]);

    $this->postJson(route('clips.store'), [
        'url' => 'https://youtu.be/dQw4w9WgXcQ',
        'start_seconds' => 10,
        'end_seconds' => 20,
        'rights_confirmed' => true,
    ])->assertServiceUnavailable()
        ->assertJsonPath('message', 'Server sedang menangani banyak permintaan. Coba lagi dalam beberapa saat.');

    Queue::assertNothingPushed();
});

test('store accepts a clip when capacity is available', function () {
    config([
        'freekliping.max_pending_per_ip' => 3,
        'freekliping.max_concurrent_clips' => 10,
    ]);

    $this->postJson(route('clips.store'), [
        'url' => 'https://youtu.be/dQw4w9WgXcQ',
        'start_seconds' => 10,
        'end_seconds' => 20,
        'rights_confirmed' => true,
    ])->assertAccepted();

    Queue::assertPushed(ProcessClip::class);
});
