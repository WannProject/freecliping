<?php

namespace App\Enums;

enum SubtitleStyle: string
{
    case WordHighlight = 'word-highlight';
    case Classic = 'classic';
    case NeonBox = 'neon-box';

    public function label(): string
    {
        return match ($this) {
            self::WordHighlight => 'Word highlight',
            self::Classic => 'Classic',
            self::NeonBox => 'Neon box',
        };
    }
}
