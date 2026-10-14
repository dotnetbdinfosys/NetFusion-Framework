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

namespace NetFusion\Core\Formal\Enums;

/**
 * First-Order Logic Sort / Type system.
 */
enum SortType: string
{
    case BOOL          = 'Bool';
    case INT           = 'Int';
    case REAL          = 'Real';
    case BITVEC        = 'BitVec';
    case ARRAY         = 'Array';
    case UNINTERPRETED = 'Uninterpreted';
}
