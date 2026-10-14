<?php
/**
 * NetFusion Framework - Enterprise Cognitive Architecture
 *
 * Cognitive buffer types interfacing memory with action in ACT-R / SOAR.
 *
 * @package    NetFusion\Core\Cognitive\Enums
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Cognitive\Enums;

enum BufferType: string
{
    case Goal      = 'goal';      // Active current intention / task context
    case Retrieval = 'retrieval'; // Slot populated by declarative query response
    case Context   = 'context';   // Environmental / sensory state
    case Action    = 'action';    // Motor / output effector request

    public function isWritable(): bool
    {
        return true;
    }
}
