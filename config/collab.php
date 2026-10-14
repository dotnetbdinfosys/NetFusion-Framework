<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Document Storage Driver
    |--------------------------------------------------------------------------
    | Options: 'database', 'array'
    */
    'driver' => env('COLLAB_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Snapshot & History Settings
    |--------------------------------------------------------------------------
    | Maximum operations to retain in in-memory session cache before pruning.
    */
    'session_history_limit' => env('COLLAB_HISTORY_LIMIT', 500),

    /*
    |--------------------------------------------------------------------------
    | WebRTC Signaling Configuration
    |--------------------------------------------------------------------------
    | Default STUN/TURN ICE servers provided to clients joining rooms.
    */
    'webrtc' => [
        'ice_servers' => [
            ['urls' => 'stun:stun.l.google.com:19302'],
            ['urls' => 'stun:global.stun.twilio.com:3478'],
        ],
        'room_peer_limit' => env('WEBRTC_ROOM_PEER_LIMIT', 20),
    ],
];
