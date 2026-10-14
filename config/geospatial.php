<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework - Geospatial & Spatial Indexing Configuration
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
    | Default Distance Measurement Unit
    |--------------------------------------------------------------------------
    |
    | Supported units: meters, kilometers, miles
    |
    */
    'default_unit' => env('GEO_DEFAULT_UNIT', 'meters'),

    /*
    |--------------------------------------------------------------------------
    | Earth Mean Spherical Radius (Meters)
    |--------------------------------------------------------------------------
    */
    'earth_radius' => (float) env('GEO_EARTH_RADIUS', 6371000.0),

    /*
    |--------------------------------------------------------------------------
    | Uber H3 Discrete Global Grid Default Resolution
    |--------------------------------------------------------------------------
    |
    | H3 Resolution ranges from 0 (coarsest, ~1100 km edge) to 15 (finest, ~0.5 m edge)
    | Resolution 8 has ~460m hexagon edge length, ideal for neighborhood-level indexing.
    |
    */
    'default_h3_resolution' => (int) env('GEO_H3_RESOLUTION', 8),

    /*
    |--------------------------------------------------------------------------
    | 2D R-Tree Spatial Index Node Capacities
    |--------------------------------------------------------------------------
    */
    'rtree_max_entries' => (int) env('GEO_RTREE_MAX_ENTRIES', 16),
    'rtree_min_entries' => (int) env('GEO_RTREE_MIN_ENTRIES', 4),
];
