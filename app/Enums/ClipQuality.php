<?php

namespace App\Enums;

enum ClipQuality: string
{
    case Source = 'source';
    case P480 = '480p';
    case P720 = '720p';
    case P1080 = '1080p';

    public function maxHeight(): ?int
    {
        return match ($this) {
            self::Source => null,
            self::P480 => 480,
            self::P720 => 720,
            self::P1080 => 1080,
        };
    }

    public function ytDlpFormat(): string
    {
        $maxHeight = $this->maxHeight();

        if ($maxHeight === null) {
            return 'bv*+ba/b';
        }

        return "bv*[height<={$maxHeight}]+ba/b[height<={$maxHeight}]/b";
    }

    public function scaleFilter(ClipAspectRatio $aspectRatio): ?string
    {
        return match ($this) {
            self::Source => null,
            self::P480 => $this->scaleFor($aspectRatio, 854, 480),
            self::P720 => $this->scaleFor($aspectRatio, 1280, 720),
            self::P1080 => $this->scaleFor($aspectRatio, 1920, 1080),
        };
    }

    private function scaleFor(ClipAspectRatio $aspectRatio, int $wideWidth, int $wideHeight): string
    {
        return match ($aspectRatio) {
            ClipAspectRatio::Original => "scale=-2:{$wideHeight}",
            ClipAspectRatio::Wide => "scale={$wideWidth}:{$wideHeight}",
            ClipAspectRatio::Vertical => "scale={$wideHeight}:{$wideWidth}",
            ClipAspectRatio::Square => "scale={$wideHeight}:{$wideHeight}",
        };
    }
}
