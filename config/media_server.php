<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework - Media Server & SFU Configuration
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default Media Codecs
    |--------------------------------------------------------------------------
    |
    | Supported video codecs: VP8, H264, VP9, AV1
    | Supported audio codecs: OPUS, PCMU, PCMA
    |
    */
    'default_video_codec' => env('MEDIA_DEFAULT_VIDEO_CODEC', 'VP8'),
    'default_audio_codec' => env('MEDIA_DEFAULT_AUDIO_CODEC', 'OPUS'),

    /*
    |--------------------------------------------------------------------------
    | SFU Room Capacities & Jitter Buffer
    |--------------------------------------------------------------------------
    */
    'max_room_peers' => (int) env('MEDIA_MAX_ROOM_PEERS', 100),
    'jitter_buffer_capacity' => (int) env('MEDIA_JITTER_BUFFER_CAPACITY', 100),

    /*
    |--------------------------------------------------------------------------
    | Security & SRTP Encryption
    |--------------------------------------------------------------------------
    */
    'enable_srtp' => (bool) env('MEDIA_ENABLE_SRTP', true),

    /*
    |--------------------------------------------------------------------------
    | Adaptive Bitrate (ABR) & HLS Segmenter
    |--------------------------------------------------------------------------
    */
    'hls_target_duration' => (float) env('MEDIA_HLS_TARGET_DURATION', 6.0),
    'recording_path' => env('MEDIA_RECORDING_PATH', 'storage/media/recordings'),
];
