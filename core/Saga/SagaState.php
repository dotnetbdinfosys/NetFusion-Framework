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

namespace NetFusion\Core\Saga;

enum SagaState: string
{
    case PENDING = 'PENDING';
    case RUNNING = 'RUNNING';
    case COMPLETED = 'COMPLETED';
    case COMPENSATING = 'COMPENSATING';
    case COMPENSATED = 'COMPENSATED';
    case FAILED = 'FAILED';
    case PAUSED = 'PAUSED';

    public function isFinal(): bool
    {
        return in_array($this, [self::COMPLETED, self::COMPENSATED, self::FAILED], true);
    }

    public function isSuccessful(): bool
    {
        return $this === self::COMPLETED;
    }

    public function isRolledBack(): bool
    {
        return $this === self::COMPENSATED;
    }
}
