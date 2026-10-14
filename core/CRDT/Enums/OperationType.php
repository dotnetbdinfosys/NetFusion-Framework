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

enum OperationType: string
{
    case INSERT = 'insert';
    case DELETE = 'delete';
    case RETAIN = 'retain';
    case UPDATE = 'update';
    case MERGE  = 'merge';
}
