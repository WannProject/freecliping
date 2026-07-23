<?php

namespace App\Enums;

enum ClipAnalysisStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
