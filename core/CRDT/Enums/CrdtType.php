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

enum CrdtType: string
{
    case LWW_REGISTER = 'lww_register';
    case PN_COUNTER   = 'pn_counter';
    case OR_SET       = 'or_set';
    case RGA_TEXT     = 'rga_text';
    case MV_REGISTER  = 'mv_register';
    case JSON_CRDT    = 'json_crdt';
}
