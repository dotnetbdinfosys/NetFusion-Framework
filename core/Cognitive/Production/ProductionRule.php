<?php
/**
 * NetFusion Framework - Enterprise Cognitive Architecture
 *
 * Procedural If-Then production rule matching against cognitive buffers.
 *
 * @package    NetFusion\Core\Cognitive\Production
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Cognitive\Production;

use NetFusion\Core\Cognitive\Enums\BufferType;

class ProductionRule
{
    /**
     * @param string $name Unique rule identifier
     * @param array<string, array<string, mixed>> $conditions Buffer pattern matches: [BufferType::value => [slot => val]]
     * @param callable $action Callback function(array &$buffers, DeclarativeMemory $dm, EpisodicMemory $em): mixed
     * @param float $utility Learned utility score U_i for conflict resolution
     */
    public function __construct(
        private readonly string $name,
        private readonly array $conditions,
        private readonly mixed $action,
        private float $utility = 0.0
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getUtility(): float
    {
        return $this->utility;
    }

    public function setUtility(float $utility): void
    {
        $this->utility = $utility;
    }

    /**
     * Check if current active buffers satisfy the production rule conditions.
     *
     * @param array<string, array<string, mixed>> $buffers [bufferName => [slot => val]]
     */
    public function matches(array $buffers): bool
    {
        foreach ($this->conditions as $bufName => $expectedSlots) {
            if (!isset($buffers[$bufName])) {
                return false;
            }

            foreach ($expectedSlots as $slotKey => $expectedVal) {
                if (!array_key_exists($slotKey, $buffers[$bufName])) {
                    return false;
                }
                if ($buffers[$bufName][$slotKey] != $expectedVal) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Fire the production rule and apply its actions to buffers and memories.
     */
    public function fire(array &$buffers, mixed ...$args): mixed
    {
        if (is_callable($this->action)) {
            return ($this->action)($buffers, ...$args);
        }
        return null;
    }
}
