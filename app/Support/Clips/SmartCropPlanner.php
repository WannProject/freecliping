<?php

namespace App\Support\Clips;

use App\Enums\ClipAspectRatio;
use App\Models\Clip;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use JsonException;

final class SmartCropPlanner
{
    public function filter(Clip $clip, string $sourceFile, int $startSeconds, int $durationSeconds): ?string
    {
        if ($clip->aspect_ratio === ClipAspectRatio::Original) {
            return null;
        }

        if ($this->mode() !== 'smart') {
            return $clip->aspect_ratio->cropFilter();
        }

        $startedAt = microtime(true);
        $points = $this->detectFocusPoints($clip, $sourceFile, $startSeconds, $durationSeconds);

        if ($points === []) {
            Log::info('smart crop fell back to center crop', [
                'clip' => $clip->uuid,
                'elapsed_ms' => $this->elapsedMs($startedAt),
            ]);

            return $clip->aspect_ratio->cropFilter();
        }

        $smoothedPoints = $this->smoothPoints($this->boundTimeline($points, $durationSeconds));

        Log::info('smart crop planned', [
            'clip' => $clip->uuid,
            'points' => count($smoothedPoints),
            'elapsed_ms' => $this->elapsedMs($startedAt),
        ]);

        return $this->animatedCropFilter($clip->aspect_ratio, $smoothedPoints);
    }

    /**
     * @return array<int, array{time: float, x: float, y: float, confidence: float}>
     */
    private function detectFocusPoints(Clip $clip, string $sourceFile, int $startSeconds, int $durationSeconds): array
    {
        $binary = $this->detectorBinary();

        if ($binary === null) {
            return [];
        }

        $outputFile = dirname($sourceFile).'/smart-crop.json';
        File::delete($outputFile);

        $command = [
            $binary,
            '--input',
            $sourceFile,
            '--start',
            (string) $startSeconds,
            '--duration',
            (string) $durationSeconds,
            '--aspect-ratio',
            $clip->aspect_ratio->value,
            '--output',
            $outputFile,
        ];

        try {
            $result = Process::timeout($this->detectorTimeout())->run($command);
        } catch (ProcessTimedOutException $exception) {
            Log::warning('smart crop detector timed out', [
                'clip' => $clip->uuid,
                'error' => $exception->getMessage(),
            ]);

            return [];
        }

        if ($result->failed()) {
            Log::warning('smart crop detector failed', [
                'clip' => $clip->uuid,
                'error' => $result->errorOutput(),
            ]);

            return [];
        }

        $content = File::exists($outputFile) ? File::get($outputFile) : $result->output();

        return $this->parseDetectorOutput($content, $durationSeconds);
    }

    /**
     * @return array<int, array{time: float, x: float, y: float, confidence: float}>
     */
    private function parseDetectorOutput(string $content, int $durationSeconds): array
    {
        try {
            $data = json_decode($content, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        $rawPoints = Arr::get($data, 'points', $data);

        if (! is_array($rawPoints)) {
            return [];
        }

        $points = [];

        foreach ($rawPoints as $point) {
            if (! is_array($point)) {
                continue;
            }

            $time = Arr::get($point, 'time');
            $x = Arr::get($point, 'x');
            $y = Arr::get($point, 'y');
            $confidence = Arr::get($point, 'confidence', 1);

            if (! is_numeric($time) || ! is_numeric($x) || ! is_numeric($y) || ! is_numeric($confidence)) {
                continue;
            }

            if ((float) $confidence < $this->minConfidence()) {
                continue;
            }

            $points[] = [
                'time' => max(0.0, min((float) $durationSeconds, (float) $time)),
                'x' => max(0.0, min(1.0, (float) $x)),
                'y' => max(0.0, min(1.0, (float) $y)),
                'confidence' => max(0.0, min(1.0, (float) $confidence)),
            ];
        }

        usort($points, fn (array $first, array $second): int => $first['time'] <=> $second['time']);

        return array_slice($points, 0, $this->maxPoints());
    }

    /**
     * @param  array<int, array{time: float, x: float, y: float, confidence: float}>  $points
     * @return array<int, array{time: float, x: float, y: float, confidence: float}>
     */
    private function boundTimeline(array $points, int $durationSeconds): array
    {
        if ($points === []) {
            return [];
        }

        $first = $points[0];
        $last = $points[array_key_last($points)];

        if ($first['time'] > 0.0) {
            array_unshift($points, [...$first, 'time' => 0.0]);
        }

        if ($last['time'] < $durationSeconds) {
            $points[] = [...$last, 'time' => (float) $durationSeconds];
        }

        return $points;
    }

    /**
     * @param  array<int, array{time: float, x: float, y: float, confidence: float}>  $points
     * @return array<int, array{time: float, x: float, y: float, confidence: float}>
     */
    private function smoothPoints(array $points): array
    {
        $previous = null;
        $smoothing = $this->smoothing();
        $smoothed = [];

        foreach ($points as $point) {
            if ($previous === null) {
                $previous = $point;
                $smoothed[] = $point;

                continue;
            }

            $point['x'] = $previous['x'] * $smoothing + $point['x'] * (1 - $smoothing);
            $point['y'] = $previous['y'] * $smoothing + $point['y'] * (1 - $smoothing);
            $previous = $point;
            $smoothed[] = $point;
        }

        return $smoothed;
    }

    /**
     * @param  array<int, array{time: float, x: float, y: float, confidence: float}>  $points
     */
    private function animatedCropFilter(ClipAspectRatio $aspectRatio, array $points): string
    {
        ['width' => $width, 'height' => $height] = $this->cropSize($aspectRatio);

        return sprintf(
            'crop=%s:%s:%s:%s',
            $width,
            $height,
            $this->positionExpression($points, 'x', 'iw', 'ow'),
            $this->positionExpression($points, 'y', 'ih', 'oh'),
        );
    }

    /**
     * @return array{width: string, height: string}
     */
    private function cropSize(ClipAspectRatio $aspectRatio): array
    {
        return match ($aspectRatio) {
            ClipAspectRatio::Wide => [
                'width' => 'min(iw\,ih*16/9)',
                'height' => 'min(ih\,iw*9/16)',
            ],
            ClipAspectRatio::Vertical => [
                'width' => 'min(iw\,ih*9/16)',
                'height' => 'min(ih\,iw*16/9)',
            ],
            ClipAspectRatio::Square => [
                'width' => 'min(iw\,ih)',
                'height' => 'min(iw\,ih)',
            ],
            ClipAspectRatio::Original => [
                'width' => 'iw',
                'height' => 'ih',
            ],
        };
    }

    /**
     * @param  array<int, array{time: float, x: float, y: float, confidence: float}>  $points
     */
    private function positionExpression(array $points, string $axis, string $inputDimension, string $outputDimension): string
    {
        $focusExpression = $this->focusExpression($points, $axis);

        return sprintf(
            'min(max(%s*%s-%s/2\,0)\,%s-%s)',
            $focusExpression,
            $inputDimension,
            $outputDimension,
            $inputDimension,
            $outputDimension,
        );
    }

    /**
     * @param  array<int, array{time: float, x: float, y: float, confidence: float}>  $points
     */
    private function focusExpression(array $points, string $axis): string
    {
        if (count($points) === 1) {
            return $this->number($points[0][$axis]);
        }

        $lastPoint = $points[count($points) - 1];
        $expression = $this->number($lastPoint[$axis]);

        for ($index = count($points) - 2; $index >= 0; $index--) {
            $current = $points[$index];
            $next = $points[$index + 1];
            $duration = max(0.001, $next['time'] - $current['time']);
            $segment = sprintf(
                '%s+(%s-%s)*(t-%s)/%s',
                $this->number($current[$axis]),
                $this->number($next[$axis]),
                $this->number($current[$axis]),
                $this->number($current['time']),
                $this->number($duration),
            );
            $expression = sprintf('if(lt(t\,%s)\,%s\,%s)', $this->number($next['time']), $segment, $expression);
        }

        return $expression;
    }

    private function number(float $value): string
    {
        return number_format($value, 3, '.', '');
    }

    private function mode(): string
    {
        $mode = config('freekliping.smart_crop.mode');

        return is_string($mode) && $mode === 'smart' ? 'smart' : 'center';
    }

    private function detectorBinary(): ?string
    {
        $binary = config('freekliping.smart_crop.detector_binary');

        return is_string($binary) && $binary !== '' ? $binary : null;
    }

    private function detectorTimeout(): int
    {
        $timeout = config('freekliping.smart_crop.detector_timeout');

        return is_int($timeout) && $timeout > 0 ? $timeout : 30;
    }

    private function minConfidence(): float
    {
        $confidence = config('freekliping.smart_crop.min_confidence');

        return is_float($confidence) || is_int($confidence)
            ? max(0.0, min(1.0, (float) $confidence))
            : 0.35;
    }

    private function maxPoints(): int
    {
        $maxPoints = config('freekliping.smart_crop.max_points');

        return is_int($maxPoints) && $maxPoints > 0 ? $maxPoints : 24;
    }

    private function smoothing(): float
    {
        $smoothing = config('freekliping.smart_crop.smoothing');

        return is_float($smoothing) || is_int($smoothing)
            ? max(0.0, min(0.95, (float) $smoothing))
            : 0.65;
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
