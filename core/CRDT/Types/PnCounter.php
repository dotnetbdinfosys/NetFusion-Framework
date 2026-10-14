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

namespace NetFusion\Core\CRDT\Types;

use NetFusion\Core\CRDT\Enums\CrdtType;

/**
 * Positive-Negative Counter (PN-Counter) CRDT.
 * Tracks per-node increments (P) and decrements (N) monotonically.
 */
class PnCounter implements CrdtInterface
{
    private string $nodeId;
    /** @var array<string, int> */
    private array $p = [];
    /** @var array<string, int> */
    private array $n = [];

    public function __construct(string $nodeId, array $p = [], array $n = [])
    {
        $this->nodeId = $nodeId;
        $this->p = $p;
        $this->n = $n;
    }

    public function getType(): CrdtType
    {
        return CrdtType::PN_COUNTER;
    }

    public function getNodeId(): string
    {
        return $this->nodeId;
    }

    public function increment(int $amount = 1): self
    {
        $amount = max(0, $amount);
        $this->p[$this->nodeId] = ($this->p[$this->nodeId] ?? 0) + $amount;
        return $this;
    }

    public function decrement(int $amount = 1): self
    {
        $amount = max(0, $amount);
        $this->n[$this->nodeId] = ($this->n[$this->nodeId] ?? 0) + $amount;
        return $this;
    }

    public function getValue(): int
    {
        $positive = (int)array_sum($this->p);
        $negative = (int)array_sum($this->n);
        return $positive - $negative;
    }

    public function getState(): array
    {
        return [
            'type'    => $this->getType()->value,
            'node_id' => $this->nodeId,
            'p'       => $this->p,
            'n'       => $this->n,
        ];
    }

    public function merge(array $remoteState): self
    {
        $remoteP = $remoteState['p'] ?? [];
        $remoteN = $remoteState['n'] ?? [];

        foreach ($remoteP as $node => $val) {
            $this->p[$node] = max($this->p[$node] ?? 0, (int)$val);
        }

        foreach ($remoteN as $node => $val) {
            $this->n[$node] = max($this->n[$node] ?? 0, (int)$val);
        }

        return $this;
    }

    public function delta(?array $sinceVectorClock = null): array
    {
        return $this->getState();
    }
}
