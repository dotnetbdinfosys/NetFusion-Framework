<?php
/**
 * NetFusion Framework - Enterprise Cognitive Architecture
 *
 * Cognitive memory classifications.
 *
 * @package    NetFusion\Core\Cognitive\Enums
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Cognitive\Enums;

enum MemoryType: string
{
    case Working     = 'working';     // Fast active buffer slots
    case Declarative = 'declarative'; // Facts, concepts & chunks with activation decay
    case Episodic    = 'episodic';    // Temporal autobiographical event traces
    case Procedural  = 'procedural';  // Production rules (If-Then knowledge)

    public function description(): string
    {
        return match ($this) {
            self::Working     => 'Active Working Memory Buffers',
            self::Declarative => 'Declarative Chunk Knowledge with Base-Level Activation',
            self::Episodic    => 'Episodic Chronological Experience Memory',
            self::Procedural  => 'Procedural Production Rules & Utility Matcher',
        };
    }
}
