<?php

namespace App\Enums;

enum SubtitleStatus: string
{
    case Burned = 'burned';
    case Unavailable = 'unavailable';
    case Failed = 'failed';
}
