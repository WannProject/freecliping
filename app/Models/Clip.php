<?php

namespace App\Models;

use App\Enums\ClipAspectRatio;
use App\Enums\ClipQuality;
use App\Enums\ClipStatus;
use App\Enums\SubtitleStatus;
use App\Enums\SubtitleStyle;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $source_url
 * @property string $youtube_video_id
 * @property string|null $title
 * @property string|null $channel
 * @property int|null $duration_seconds
 * @property int $start_seconds
 * @property int $end_seconds
 * @property ClipAspectRatio $aspect_ratio
 * @property ClipQuality $quality
 * @property bool $subtitles_enabled
 * @property SubtitleStatus|null $subtitle_status
 * @property SubtitleStyle $subtitle_style
 * @property string $subtitle_font_family
 * @property string $subtitle_font_size
 * @property string $subtitle_position
 * @property string $subtitle_color
 * @property ClipStatus $status
 * @property int $progress
 * @property string|null $output_disk
 * @property string|null $output_path
 * @property string|null $custom_file_name
 * @property int|null $output_size_bytes
 * @property CarbonImmutable|null $output_expires_at
 * @property string|null $error_message
 * @property string|null $requested_ip
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Clip extends Model
{
    protected $fillable = [
        'uuid',
        'source_url',
        'youtube_video_id',
        'title',
        'channel',
        'duration_seconds',
        'start_seconds',
        'end_seconds',
        'aspect_ratio',
        'quality',
        'status',
        'progress',
        'subtitles_enabled',
        'subtitle_status',
        'subtitle_style',
        'subtitle_font_family',
        'subtitle_font_size',
        'subtitle_position',
        'subtitle_color',
        'output_disk',
        'output_path',
        'custom_file_name',
        'output_size_bytes',
        'output_expires_at',
        'error_message',
        'requested_ip',
    ];

    protected $attributes = [
        'aspect_ratio' => 'original',
        'quality' => 'source',
        'subtitle_style' => 'word-highlight',
        'subtitle_font_family' => 'dejavu-sans',
        'subtitle_font_size' => 'medium',
        'subtitle_position' => 'bottom',
        'subtitle_color' => 'white',
        'status' => 'queued',
        'progress' => 0,
    ];

    protected static function booted(): void
    {
        static::creating(function (Clip $clip): void {
            if (! $clip->uuid) {
                $clip->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Clips that still occupy queue/worker capacity: queued or processing.
     *
     * @param  Builder<Clip>  $query
     * @return Builder<Clip>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', [ClipStatus::Queued, ClipStatus::Processing]);
    }

    /**
     * Pending clips attributed to a single requesting IP.
     *
     * @param  Builder<Clip>  $query
     * @return Builder<Clip>
     */
    public function scopePendingForIp(Builder $query, string $ip): Builder
    {
        return $query->whereIn('status', [ClipStatus::Queued, ClipStatus::Processing])
            ->where('requested_ip', $ip);
    }

    public function fileName(): string
    {
        $baseName = $this->custom_file_name
            ?: sprintf('clip-%s-%ss', $this->youtube_video_id, $this->start_seconds);

        return Str::of($baseName)
            ->replaceMatches('/\.mp4$/i', '')
            ->append('.mp4')
            ->toString();
    }

    public function downloadUrl(): ?string
    {
        if (
            $this->status !== ClipStatus::Completed
            || ! $this->output_path
            || ! $this->output_expires_at
            || $this->output_expires_at->isPast()
        ) {
            return null;
        }

        return URL::temporarySignedRoute(
            'clips.download',
            $this->output_expires_at,
            ['clip' => $this],
        );
    }

    public function previewUrl(): ?string
    {
        if (
            $this->status !== ClipStatus::Completed
            || ! $this->output_path
            || ! $this->output_expires_at
            || $this->output_expires_at->isPast()
        ) {
            return null;
        }

        return URL::temporarySignedRoute(
            'clips.preview',
            $this->output_expires_at,
            ['clip' => $this],
        );
    }

    protected function casts(): array
    {
        return [
            'aspect_ratio' => ClipAspectRatio::class,
            'quality' => ClipQuality::class,
            'status' => ClipStatus::class,
            'subtitles_enabled' => 'boolean',
            'subtitle_status' => SubtitleStatus::class,
            'subtitle_style' => SubtitleStyle::class,
            'output_expires_at' => 'immutable_datetime',
        ];
    }
}
