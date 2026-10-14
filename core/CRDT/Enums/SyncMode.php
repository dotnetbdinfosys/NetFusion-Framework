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

namespace NetFusion\Core\CRDT\Enums;

enum SyncMode: string
{
    case STATE_BASED     = 'state_based';
    case DELTA_BASED     = 'delta_based';
    case OPERATION_BASED = 'operation_based';
}
