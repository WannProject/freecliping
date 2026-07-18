<?php

namespace App\Enums;

enum ClipAspectRatio: string
{
    case Original = 'original';
    case Wide = '16:9';
    case Vertical = '9:16';
    case Square = '1:1';

    public function cropFilter(): ?string
    {
        return match ($this) {
            self::Original => null,
            self::Wide => 'crop=min(iw\,ih*16/9):min(ih\,iw*9/16)',
            self::Vertical => 'crop=min(iw\,ih*9/16):min(ih\,iw*16/9)',
            self::Square => 'crop=min(iw\,ih):min(iw\,ih)',
        };
    }
}
