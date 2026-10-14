<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework — Configuration
 |--------------------------------------------------------------------------
 |
 | Author    : Md. Atiquzzaman Ashish
 | Company   : DotNetBD
 | Website   : www.dotnetbd.com
 | Copyright : © DotNetBD. All rights reserved.
 | License   : MIT
 | Version   : 1.0.0
 |
 |--------------------------------------------------------------------------
 */

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Real-Time Collaboration & CRDT Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for NetFusion's Enterprise CRDT Document Engine, WebRTC
    | Mesh Coordinator & Operational Transformation (OT) Runtime.
    |
    */

    'presence_timeout_seconds' => 30.0,

    'snapshot_interval_revisions' => 50,

    'max_mesh_peers_per_room' => 64,

    'default_room' => 'general',

    'default_color_palette' => [
        '#ef4444', '#f97316', '#eab308', '#22c55e', '#06b6d4',
        '#3b82f6', '#6366f1', '#a855f7', '#ec4899', '#f43f5e',
    ],
];
