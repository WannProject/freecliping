<?php

namespace App\Models;

use App\Enums\ClipAspectRatio;
use App\Enums\ClipQuality;
use App\Enums\LocalWorkerJobStatus;
use App\Enums\SubtitleStyle;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $source_type
 * @property string $source_url
 * @property string|null $youtube_video_id
 * @property string|null $title
 * @property string|null $channel
 * @property int|null $duration_seconds
 * @property int $start_seconds
 * @property int $end_seconds
 * @property ClipAspectRatio $aspect_ratio
 * @property ClipQuality $quality
 * @property bool $subtitles_enabled
 * @property SubtitleStyle $subtitle_style
 * @property string $subtitle_font_family
 * @property string $subtitle_font_size
 * @property string $subtitle_position
 * @property string $subtitle_color
 * @property bool $sync_output
 * @property LocalWorkerJobStatus $status
 * @property int $progress
 * @property string|null $local_output_path
 * @property string $worker_token_hash
 * @property array<string, mixed>|null $manifest
 * @property string|null $error_message
 * @property string|null $requested_ip
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
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
        'subtitle_font_family',
        'subtitle_font_size',
        'subtitle_position',
        'subtitle_color',
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
        'subtitle_font_family' => 'dejavu-sans',
        'subtitle_font_size' => 'medium',
        'subtitle_position' => 'bottom',
        'subtitle_color' => 'white',
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
