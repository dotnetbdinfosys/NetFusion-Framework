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
    | Default Vector Store
    |--------------------------------------------------------------------------
    |
    | Supported: "array", "database"
    |
    */
    'store' => env('VECTOR_STORE', 'array'),

    /*
    |--------------------------------------------------------------------------
    | Default Distance Metric
    |--------------------------------------------------------------------------
    |
    | Supported: "cosine", "euclidean", "dot_product", "manhattan"
    |
    */
    'default_metric' => env('VECTOR_METRIC', 'cosine'),

    /*
    |--------------------------------------------------------------------------
    | HNSW Graph Parameters
    |--------------------------------------------------------------------------
    */
    'hnsw' => [
        'M' => (int)env('HNSW_M', 16),
        'ef_construction' => (int)env('HNSW_EF_CONSTRUCTION', 64),
        'ef_search' => (int)env('HNSW_EF_SEARCH', 32),
    ],

    /*
    |--------------------------------------------------------------------------
    | Embedding Providers & Configuration
    |--------------------------------------------------------------------------
    */
    'embeddings' => [
        'default' => env('VECTOR_EMBEDDING_DRIVER', 'mock'),

        'drivers' => [
            'mock' => [
                'dimension' => (int)env('EMBEDDING_MOCK_DIM', 64),
                'model' => 'mock-embedding-v1',
            ],

            'openai' => [
                'api_key' => env('OPENAI_API_KEY', ''),
                'model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
                'dimension' => 1536,
                'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            ],

            'ollama' => [
                'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
                'model' => env('OLLAMA_EMBEDDING_MODEL', 'nomic-embed-text'),
                'dimension' => 768,
            ],

            'huggingface' => [
                'api_key' => env('HUGGINGFACE_API_KEY', ''),
                'model' => env('HUGGINGFACE_EMBEDDING_MODEL', 'sentence-transformers/all-MiniLM-L6-v2'),
                'dimension' => 384,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | RAG Pipeline Settings
    |--------------------------------------------------------------------------
    */
    'rag' => [
        'top_k' => (int)env('RAG_TOP_K', 3),
        'type' => env('RAG_SEARCH_TYPE', 'hybrid'),
        'rerank' => (bool)env('RAG_RERANK', true),
        'min_score' => (float)env('RAG_MIN_SCORE', 0.0),
        'rrf_k' => 60,
    ],
];
