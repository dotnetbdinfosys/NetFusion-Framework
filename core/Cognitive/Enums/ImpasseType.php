<?php
/**
 * NetFusion Framework - Enterprise Cognitive Architecture
 *
 * SOAR architecture cognitive impasse classifications.
 *
 * @package    NetFusion\Core\Cognitive\Enums
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Cognitive\Enums;

enum ImpasseType: string
{
    case Tie               = 'tie';                // Multiple competing rules share identical maximum utility
    case NoChange          = 'no_change';          // No production rule matched the current buffer contents
    case Conflict          = 'conflict';           // Contradictory operator proposals
    case ConstraintFailure = 'constraint_failure'; // Goal constraints violated

    public function requiresSubgoal(): bool
    {
        return true;
    }
}
