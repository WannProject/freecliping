<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Clip Processing Limits
    |--------------------------------------------------------------------------
    |
    | Keep these values conservative for the first VPS deployment. They protect
    | the application from expensive background work before a clip job starts.
    |
    */

    'max_clip_length' => (int) env('FREEKLIPING_MAX_CLIP_LENGTH', 180),

    'retention_hours' => (int) env('FREEKLIPING_RETENTION_HOURS', 1),

    'yt_dlp_binary' => env('FREEKLIPING_YT_DLP_BINARY', 'yt-dlp'),

    'ffmpeg_binary' => env('FREEKLIPING_FFMPEG_BINARY', 'ffmpeg'),

    'metadata_timeout' => (int) env('FREEKLIPING_METADATA_TIMEOUT', 20),

    'processing_timeout' => (int) env('FREEKLIPING_PROCESSING_TIMEOUT', 600),

    'download_buffer_seconds' => (int) env('FREEKLIPING_DOWNLOAD_BUFFER_SECONDS', 3),

    'output_disk' => env('FREEKLIPING_OUTPUT_DISK', env('FILESYSTEM_DISK', 'local')),

];
