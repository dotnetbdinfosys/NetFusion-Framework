<?php
/**
 * NetFusion Framework - Enterprise Zero-Knowledge Rollup (ZK-Rollup) Fabric
 *
 * Status lifecycle of a Rollup batch.
 *
 * @package    NetFusion\Core\Rollup\Enums
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Rollup\Enums;

enum RollupBatchStatus: string
{
    case PENDING   = 'pending';
    case COMPUTING = 'computing';
    case PROVED    = 'proved';
    case COMMITTED = 'committed';
    case REJECTED  = 'rejected';

    /**
     * Determine if batch has reached a finalized state.
     */
    public function isFinalized(): bool
    {
        return $this === self::COMMITTED || $this === self::REJECTED;
    }

    /**
     * Validate allowable state transitions.
     */
    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::PENDING   => in_array($next, [self::COMPUTING, self::REJECTED], true),
            self::COMPUTING => in_array($next, [self::PROVED, self::REJECTED], true),
            self::PROVED    => in_array($next, [self::COMMITTED, self::REJECTED], true),
            self::COMMITTED, self::REJECTED => false,
        };
    }
}
