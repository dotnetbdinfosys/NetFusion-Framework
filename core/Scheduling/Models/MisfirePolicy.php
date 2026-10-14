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

namespace NetFusion\Core\Scheduling\Models;

final class MisfirePolicy
{
    public const FIRE_NOW        = 'fire_now';
    public const IGNORE          = 'ignore';
    public const RESCHEDULE_NEXT = 'reschedule_next';
}
