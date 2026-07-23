<?php

namespace App\Models;

use App\Enums\ClipAnalysisStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ClipAnalysis extends Model
{
    protected $fillable = [
        'uuid',
        'source_url',
        'youtube_video_id',
        'title',
        'channel',
        'duration_seconds',
        'thumbnail_url',
        'status',
        'progress',
        'transcript_language',
        'recommendations',
        'error_message',
        'requested_ip',
        'completed_at',
    ];

    protected $attributes = [
        'status' => 'queued',
        'progress' => 0,
    ];

    protected static function booted(): void
    {
        static::creating(function (ClipAnalysis $analysis): void {
            if (! $analysis->uuid) {
                $analysis->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ClipAnalysisStatus::Queued->value,
            ClipAnalysisStatus::Processing->value,
        ]);
    }

    public function scopePendingForIp(Builder $query, string $ip): Builder
    {
        return $query->pending()->where('requested_ip', $ip);
    }

    protected function casts(): array
    {
        return [
            'status' => ClipAnalysisStatus::class,
            'recommendations' => 'array',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
