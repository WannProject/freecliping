<?php

namespace App\Console\Commands;

use App\Enums\ClipAspectRatio;
use App\Enums\ClipQuality;
use App\Models\Clip;
use App\Support\Clips\SmartCropPlanner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use ValueError;

#[Signature('clips:smart-crop:benchmark
    {source : Local source video path}
    {--aspect-ratio=9:16 : Target aspect ratio: original, 16:9, 9:16, or 1:1}
    {--start=0 : Local start offset in seconds}
    {--duration=30 : Clip duration in seconds}')]
#[Description('Benchmark smart crop planning against center crop fallback.')]
class BenchmarkSmartCrop extends Command
{
    public function handle(SmartCropPlanner $smartCropPlanner): int
    {
        $source = (string) $this->argument('source');
        $aspectRatio = $this->aspectRatio();
        $startSeconds = max(0, (int) $this->option('start'));
        $durationSeconds = max(1, (int) $this->option('duration'));

        if ($aspectRatio === null) {
            return self::FAILURE;
        }

        if (! file_exists($source)) {
            $this->error("Source file does not exist: {$source}");

            return self::FAILURE;
        }

        $clip = new Clip([
            'uuid' => (string) Str::uuid(),
            'source_url' => $source,
            'youtube_video_id' => 'benchmark',
            'start_seconds' => $startSeconds,
            'end_seconds' => $startSeconds + $durationSeconds,
            'aspect_ratio' => $aspectRatio,
            'quality' => ClipQuality::Source,
        ]);

        $originalMode = config('freekliping.smart_crop.mode');

        config(['freekliping.smart_crop.mode' => 'center']);
        $centerStartedAt = microtime(true);
        $centerFilter = $smartCropPlanner->filter($clip, $source, $startSeconds, $durationSeconds);
        $centerMs = $this->elapsedMs($centerStartedAt);

        config(['freekliping.smart_crop.mode' => 'smart']);
        $smartStartedAt = microtime(true);
        $smartFilter = $smartCropPlanner->filter($clip, $source, $startSeconds, $durationSeconds);
        $smartMs = $this->elapsedMs($smartStartedAt);

        config(['freekliping.smart_crop.mode' => $originalMode]);

        $this->table(
            ['Mode', 'Elapsed ms', 'Filter'],
            [
                ['center', $centerMs, $centerFilter ?? 'none'],
                ['smart', $smartMs, $smartFilter ?? 'none'],
            ],
        );

        return self::SUCCESS;
    }

    private function aspectRatio(): ?ClipAspectRatio
    {
        try {
            return ClipAspectRatio::from((string) $this->option('aspect-ratio'));
        } catch (ValueError) {
            $this->error('Invalid aspect ratio. Use original, 16:9, 9:16, or 1:1.');

            return null;
        }
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
