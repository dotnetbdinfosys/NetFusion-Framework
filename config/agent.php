<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Agent Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for NetFusion's Enterprise AI Autonomous Agent Runtime.
    |
    */
    'default_agent_name' => 'NetFusionAgent',

    'max_iterations' => 15,

    'tool_timeout_seconds' => 30,

    'max_memory_window' => 20,

    'semantic_threshold' => 0.05,

    'compact_observations' => true,

    'observation_char_limit' => 1500,

    /*
    |--------------------------------------------------------------------------
    | Auto-Discovered Tool Providers
    |--------------------------------------------------------------------------
    |
    | Classes whose public methods will be dynamically reflected into AI tools.
    |
    */
    'auto_synthesize_classes' => [
        // App\Services\CalculationService::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Directory for Persistent Working and Semantic Memory
    |--------------------------------------------------------------------------
    */
    'memory_storage_path' => storage_path('agent/memory'),
];
