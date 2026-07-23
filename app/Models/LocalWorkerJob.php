<?php

namespace App\Models;

use App\Enums\ClipAspectRatio;
use App\Enums\ClipQuality;
use App\Enums\LocalWorkerJobStatus;
use App\Enums\SubtitleStyle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LocalWorkerJob extends Model
{
    protected $fillable = [
        'uuid',
        'source_type',
        'source_url',
        'youtube_video_id',
        'title',
        'channel',
        'duration_seconds',
        'start_seconds',
        'end_seconds',
        'aspect_ratio',
        'quality',
        'subtitles_enabled',
        'subtitle_style',
        'sync_output',
        'status',
        'progress',
        'local_output_path',
        'worker_token_hash',
        'manifest',
        'error_message',
        'requested_ip',
        'completed_at',
    ];

    protected $hidden = [
        'worker_token_hash',
    ];

    protected $attributes = [
        'source_type' => 'youtube',
        'aspect_ratio' => 'original',
        'quality' => 'source',
        'subtitles_enabled' => false,
        'subtitle_style' => 'word-highlight',
        'sync_output' => false,
        'status' => 'queued',
        'progress' => 0,
    ];

    protected static function booted(): void
    {
        static::creating(function (LocalWorkerJob $job): void {
            if (! $job->uuid) {
                $job->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function matchesWorkerToken(string $token): bool
    {
        return hash_equals($this->worker_token_hash, hash('sha256', $token));
    }

    protected function casts(): array
    {
        return [
            'aspect_ratio' => ClipAspectRatio::class,
            'quality' => ClipQuality::class,
            'subtitles_enabled' => 'boolean',
            'subtitle_style' => SubtitleStyle::class,
            'sync_output' => 'boolean',
            'status' => LocalWorkerJobStatus::class,
            'manifest' => 'array',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
