<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : config/graphql.php
| Module    : GraphQL
| Purpose   : Configuration for GraphQL engine, schema routing & playground.
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

return [

    /*
    |--------------------------------------------------------------------------
    | Default Schema & Routing
    |--------------------------------------------------------------------------
    |
    | Default schema name and route prefix for GraphQL endpoints.
    |
    */
    'default_schema' => env('GRAPHQL_SCHEMA', 'default'),
    'route'          => env('GRAPHQL_ROUTE', '/graphql'),

    /*
    |--------------------------------------------------------------------------
    | GraphiQL Interactive Playground
    |--------------------------------------------------------------------------
    |
    | Enables browser-based GraphiQL IDE playground on GET /graphql requests.
    |
    */
    'playground' => (bool) env('GRAPHQL_PLAYGROUND', true),

    /*
    |--------------------------------------------------------------------------
    | Security & Execution Limits
    |--------------------------------------------------------------------------
    |
    | Maximum query depth, complexity thresholds, and introspection flags.
    |
    */
    'max_depth'      => (int) env('GRAPHQL_MAX_DEPTH', 15),
    'max_complexity' => (int) env('GRAPHQL_MAX_COMPLEXITY', 1000),
    'introspection'  => (bool) env('GRAPHQL_INTROSPECTION', true),

];
