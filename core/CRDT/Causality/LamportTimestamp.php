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

namespace NetFusion\Core\CRDT\Causality;

/**
 * Lamport logical clock primitive for strict partial order across distributed nodes.
 */
class LamportTimestamp
{
    private int $counter;
    private string $nodeId;

    public function __construct(int $counter = 0, string $nodeId = '')
    {
        $this->counter = $counter;
        $this->nodeId  = $nodeId;
    }

    public function getCounter(): int
    {
        return $this->counter;
    }

    public function getNodeId(): string
    {
        return $this->nodeId;
    }

    /**
     * Increment clock locally on node event.
     */
    public function tick(): self
    {
        $this->counter++;
        return $this;
    }

    /**
     * Update clock based on received timestamp from peer: max(local, remote) + 1.
     */
    public function witness(int $remoteCounter): self
    {
        $this->counter = max($this->counter, $remoteCounter) + 1;
        return $this;
    }

    /**
     * Compare this timestamp with another.
     * Returns:
     *  -1 if $this < $other
     *   1 if $this > $other
     *   0 if identical
     */
    public function compareTo(LamportTimestamp $other): int
    {
        if ($this->counter < $other->counter) {
            return -1;
        }
        if ($this->counter > $other->counter) {
            return 1;
        }
        // Tie-break by nodeId lexicographically
        return strcmp($this->nodeId, $other->nodeId);
    }

    public function toArray(): array
    {
        return [
            'counter' => $this->counter,
            'node_id' => $this->nodeId,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (int)($data['counter'] ?? 0),
            (string)($data['node_id'] ?? '')
        );
    }
}
