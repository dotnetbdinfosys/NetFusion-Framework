<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
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
    | Multi-Modal Vector Knowledge Graph & Hybrid Semantic RAG Configuration
    |--------------------------------------------------------------------------
    */

    'bm25' => [
        'k1' => (float) env('GRAPHRAG_BM25_K1', 1.5),
        'b'  => (float) env('GRAPHRAG_BM25_B', 0.75),
    ],

    'fusion' => [
        'default_strategy' => env('GRAPHRAG_FUSION', 'rrf'), // 'rrf' or 'weighted_score'
        'rrf_constant'     => (int) env('GRAPHRAG_RRF_K', 60),
        'weights' => [
            'dense'  => 0.50,
            'sparse' => 0.30,
            'graph'  => 0.20,
        ],
    ],

    'reranking' => [
        'enabled'          => true,
        'centrality_bonus' => 0.15,
        'diversity_lambda' => 0.80, // 1.0 = pure relevance, 0.0 = pure diversity
    ],

    'chunking' => [
        'default_size'    => 500,
        'default_overlap' => 100,
    ],
];
