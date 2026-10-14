<?php
/**
 * NetFusion Framework - Enterprise Zero-Knowledge Rollup (ZK-Rollup) Fabric
 *
 * Types of valid state transitions supported in the rollup pipeline.
 *
 * @package    NetFusion\Core\Rollup\Enums
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Rollup\Enums;

enum StateTransitionType: string
{
    case TRANSFER     = 'transfer';
    case MINT         = 'mint';
    case BURN         = 'burn';
    case STATE_UPDATE = 'state_update';

    /**
     * Check if transition modifies balance.
     */
    public function affectsBalance(): bool
    {
        return $this !== self::STATE_UPDATE;
    }
}
