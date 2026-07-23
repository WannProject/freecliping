<?php

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

test('store does not enforce a per-minute generation rate limit', function () {
    config([
        'freekliping.max_pending_per_ip' => 100,
        'freekliping.max_concurrent_clips' => 100,
    ]);

    $payload = [
        'url' => 'https://youtu.be/dQw4w9WgXcQ',
        'start_seconds' => 10,
        'end_seconds' => 20,
        'rights_confirmed' => true,
    ];

    $this->postJson(route('clips.store'), $payload)->assertAccepted();
    $this->postJson(route('clips.store'), $payload)->assertAccepted();
    $this->postJson(route('clips.store'), $payload)->assertAccepted();
});
