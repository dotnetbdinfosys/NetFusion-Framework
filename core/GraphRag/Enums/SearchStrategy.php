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

namespace NetFusion\Core\GraphRag\Enums;

enum SearchStrategy: string
{
    case DENSE_VECTOR      = 'dense_vector';
    case SPARSE_BM25       = 'sparse_bm25';
    case GRAPH_NEIGHBORHOOD = 'graph_neighborhood';
    case HYBRID_TRIFECTA   = 'hybrid_trifecta';
}
