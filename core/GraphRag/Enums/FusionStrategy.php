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

enum FusionStrategy: string
{
    case RECIPROCAL_RANK_FUSION = 'rrf';
    case WEIGHTED_SCORE          = 'weighted_score';
    case GRAPH_EXPANSION         = 'graph_expansion';
    case CROSS_ENCODER_RERANK    = 'cross_encoder_rerank';
}
