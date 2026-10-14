<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework - Event Sourcing & CQRS Configuration
|--------------------------------------------------------------------------
*/

return [
    /*
    |--------------------------------------------------------------------------
    | Default Event Store Driver
    |--------------------------------------------------------------------------
    | Supported: "database", "in_memory"
    */
    'default' => env('EVENT_STORE_DRIVER', 'database'),

    'tables' => [
        'streams' => 'event_streams',
        'events'  => 'stored_events',
    ],

    /*
    |--------------------------------------------------------------------------
    | Projections (Read Models)
    |--------------------------------------------------------------------------
    | Projections automatically registered into ProjectionManager.
    */
    'projections' => [
        // Example: App\Projections\OrderSummaryProjection::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | CQRS Command & Query Bus
    |--------------------------------------------------------------------------
    | Global middleware applied before dispatching commands or queries.
    */
    'command_middleware' => [
        // Example: NetFusion\Core\EventSourcing\Middleware\LoggingMiddleware::class,
    ],

    'query_middleware' => [
    ],
];