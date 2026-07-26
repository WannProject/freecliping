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

    // yt-dlp needs a JavaScript runtime to fully extract YouTube metadata and
    // captions. Without it, captions silently go missing (see yt-dlp warning
    // about deprecated JS-less extraction). Node is the most common runtime.
    'yt_dlp_js_runtime' => env('FREEKLIPING_YT_DLP_JS_RUNTIME', 'node'),

    'ffmpeg_binary' => env('FREEKLIPING_FFMPEG_BINARY', 'ffmpeg'),

    // libx264 encoding preset. The default ("medium") is slow on a VPS;
    // "veryfast" cuts encode time roughly 3-5x with negligible quality loss
    // for short clips. Stream-copy is used automatically when no filter
    // (crop/scale/subtitles) is needed, skipping the encode entirely.
    'ffmpeg_preset' => env('FREEKLIPING_FFMPEG_PRESET', 'veryfast'),

    // Preferred caption language (BCP-47 code). Falls back to id, id-orig, then en.
    'subtitle_language' => env('FREEKLIPING_SUBTITLE_LANGUAGE', 'id'),

    'metadata_timeout' => (int) env('FREEKLIPING_METADATA_TIMEOUT', 45),

    'processing_timeout' => (int) env('FREEKLIPING_PROCESSING_TIMEOUT', 600),

    'download_buffer_seconds' => (int) env('FREEKLIPING_DOWNLOAD_BUFFER_SECONDS', 3),

    'smart_crop' => [
        'mode' => env('FREEKLIPING_CROP_MODE', 'center'),
        'detector_binary' => env('FREEKLIPING_SMART_CROP_DETECTOR_BINARY', base_path('app/Support/Clips/smart_crop_detect.py')),
        'detector_model' => env('FREEKLIPING_SMART_CROP_MODEL'),
        'detector_timeout' => (int) env('FREEKLIPING_SMART_CROP_DETECTOR_TIMEOUT', 30),
        'min_confidence' => (float) env('FREEKLIPING_SMART_CROP_MIN_CONFIDENCE', 0.35),
        'max_points' => (int) env('FREEKLIPING_SMART_CROP_MAX_POINTS', 24),
        'smoothing' => (float) env('FREEKLIPING_SMART_CROP_SMOOTHING', 0.65),
    ],

    'output_disk' => env('FREEKLIPING_OUTPUT_DISK', env('FILESYSTEM_DISK', 'local')),

    'support_url' => env('FREEKLIPING_SUPPORT_URL', 'https://saweria.co/freekliping'),

];
