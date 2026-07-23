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

    /*
    |--------------------------------------------------------------------------
    | Abuse Control
    |--------------------------------------------------------------------------
    |
    | The app is login-less and public, so load and flooding must be bounded
    | before expensive yt-dlp/ffmpeg work starts.
    |
    | - max_concurrent_clips: global cap on queued + processing clips. Once
    |   reached, new requests get a 503 "overloaded" response.
    | - max_pending_per_ip: per-IP cap on in-flight clips, stops a single client
    |   from flooding the queue (429 once exceeded).
    | - prune_after_hours: how long completed/failed records are kept after
    |   their output expires before the pruner deletes them.
    |
    */

    'max_concurrent_clips' => (int) env('FREEKLIPING_MAX_CONCURRENT_CLIPS', 10),

    'max_pending_per_ip' => (int) env('FREEKLIPING_MAX_PENDING_PER_IP', 3),

    'max_concurrent_analyses' => (int) env('FREEKLIPING_MAX_CONCURRENT_ANALYSES', 8),

    'max_pending_analyses_per_ip' => (int) env('FREEKLIPING_MAX_PENDING_ANALYSES_PER_IP', 2),

    'max_analysis_video_length' => (int) env('FREEKLIPING_MAX_ANALYSIS_VIDEO_LENGTH', 7200),

    'analysis_retention_hours' => (int) env('FREEKLIPING_ANALYSIS_RETENTION_HOURS', 24),

    'prune_after_hours' => (int) env('FREEKLIPING_PRUNE_AFTER_HOURS', 24),

    'yt_dlp_binary' => env('FREEKLIPING_YT_DLP_BINARY', 'yt-dlp'),

    'ffmpeg_binary' => env('FREEKLIPING_FFMPEG_BINARY', 'ffmpeg'),

    // Preferred caption language (BCP-47 code). Falls back to id, id-orig, then en.
    'subtitle_language' => env('FREEKLIPING_SUBTITLE_LANGUAGE', 'id'),

    'metadata_timeout' => (int) env('FREEKLIPING_METADATA_TIMEOUT', 20),

    'processing_timeout' => (int) env('FREEKLIPING_PROCESSING_TIMEOUT', 600),

    'download_buffer_seconds' => (int) env('FREEKLIPING_DOWNLOAD_BUFFER_SECONDS', 3),

    'output_disk' => env('FREEKLIPING_OUTPUT_DISK', env('FILESYSTEM_DISK', 'local')),

];
