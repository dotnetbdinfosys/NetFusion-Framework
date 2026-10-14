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
 * AST Term node classification.
 */
enum TermKind: string
{
    case VARIABLE   = 'VARIABLE';
    case CONSTANT   = 'CONSTANT';
    case OPERATION  = 'OPERATION';
    case QUANTIFIER = 'QUANTIFIER';
}
