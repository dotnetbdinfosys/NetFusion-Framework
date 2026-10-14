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

namespace NetFusion\Core\Auth\Policy;

enum Effect: string
{
    case ALLOW = 'ALLOW';
    case DENY = 'DENY';

    public function isAllow(): bool
    {
        return $this === self::ALLOW;
    }

    public function isDeny(): bool
    {
        return $this === self::DENY;
    }
}
