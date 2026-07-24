<?php

namespace App\Models;

use App\Enums\ClipAnalysisStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $source_url
 * @property string $youtube_video_id
 * @property string $title
 * @property string $channel
 * @property int $duration_seconds
 * @property string|null $thumbnail_url
 * @property ClipAnalysisStatus $status
 * @property int $progress
 * @property string|null $transcript_language
 * @property array<int, array<string, mixed>>|null $recommendations
 * @property string|null $error_message
 * @property string|null $requested_ip
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
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

    /**
     * @param  Builder<ClipAnalysis>  $query
     * @return Builder<ClipAnalysis>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ClipAnalysisStatus::Queued,
            ClipAnalysisStatus::Processing,
        ]);
    }

    /**
     * @param  Builder<ClipAnalysis>  $query
     * @return Builder<ClipAnalysis>
     */
    public function scopePendingForIp(Builder $query, string $ip): Builder
    {
        return $query->whereIn('status', [
            ClipAnalysisStatus::Queued,
            ClipAnalysisStatus::Processing,
        ])
            ->where('requested_ip', $ip);
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
