<?php

namespace App\Models;

use App\Enums\ClipAspectRatio;
use App\Enums\ClipQuality;
use App\Enums\ClipStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

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

    protected function casts(): array
    {
        return [
            'aspect_ratio' => ClipAspectRatio::class,
            'quality' => ClipQuality::class,
            'status' => ClipStatus::class,
            'output_expires_at' => 'immutable_datetime',
        ];
    }
}
