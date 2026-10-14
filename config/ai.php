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

/*
|--------------------------------------------------------------------------
| NetFusion Framework Configuration
|--------------------------------------------------------------------------
|
| File      : config/ai.php
| Module    : Enterprise AI Agent Engine & Vector Memory
| Purpose   : LLM providers, Autonomous ReAct agents, and Vector Memory config.
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
    | Default AI LLM Provider
    |--------------------------------------------------------------------------
    | Supported: "mock", "openai", "anthropic", "gemini"
    */
    'default' => env('AI_DEFAULT_DRIVER', 'mock'),

    /*
    |--------------------------------------------------------------------------
    | AI LLM Provider Configurations
    |--------------------------------------------------------------------------
    */
    'drivers' => [

        'mock' => [
            'responses' => [],
        ],

        'openai' => [
            'api_key'          => env('OPENAI_API_KEY', ''),
            'base_url'         => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'model'            => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'embedding_model'  => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
            'timeout'          => (int) env('OPENAI_TIMEOUT', 30),
        ],

        'anthropic' => [
            'api_key'          => env('ANTHROPIC_API_KEY', ''),
            'base_url'         => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
            'model'            => env('ANTHROPIC_MODEL', 'claude-3-5-sonnet-20241022'),
            'version'          => env('ANTHROPIC_VERSION', '2023-06-01'),
            'timeout'          => (int) env('ANTHROPIC_TIMEOUT', 30),
        ],

        'gemini' => [
            'api_key'          => env('GEMINI_API_KEY', ''),
            'base_url'         => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'model'            => env('GEMINI_MODEL', 'gemini-1.5-flash'),
            'embedding_model'  => env('GEMINI_EMBEDDING_MODEL', 'text-embedding-004'),
            'timeout'          => (int) env('GEMINI_TIMEOUT', 30),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | High-Dimensional Vector Store Configurations
    |--------------------------------------------------------------------------
    | Supported: "array", "database"
    */
    'vector_store' => [
        'default' => env('AI_VECTOR_STORE', 'array'),

        'stores' => [
            'array' => [],

            'database' => [
                'table' => 'ai_vector_documents',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Memory Configuration
    |--------------------------------------------------------------------------
    */
    'memory' => [
        'default' => 'buffer',
        'window_size' => (int) env('AI_MEMORY_WINDOW_SIZE', 10),
    ],

];
