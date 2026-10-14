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

namespace NetFusion\Core\Data\Crdt;

use InvalidArgumentException;
use NetFusion\Core\Data\Crdt\Contracts\CrdtInterface;

class PNCounter implements CrdtInterface
{
    /**
     * @param array<string, int> $positive Map of node => positive increments
     * @param array<string, int> $negative Map of node => decrements
     * @param string             $localNodeId
     */
    public function __construct(
        protected array $positive = [],
        protected array $negative = [],
        protected string $localNodeId = 'local'
    ) {
    }

    public function setLocalNodeId(string $nodeId): self
    {
        $this->localNodeId = $nodeId;
        return $this;
    }

    public function increment(int $amount = 1, ?string $nodeId = null): self
    {
        if ($amount < 0) {
            return $this->decrement(-$amount, $nodeId);
        }
        $node = $nodeId ?? $this->localNodeId;
        $this->positive[$node] = ($this->positive[$node] ?? 0) + $amount;
        return $this;
    }

    public function decrement(int $amount = 1, ?string $nodeId = null): self
    {
        if ($amount < 0) {
            return $this->increment(-$amount, $nodeId);
        }
        $node = $nodeId ?? $this->localNodeId;
        $this->negative[$node] = ($this->negative[$node] ?? 0) + $amount;
        return $this;
    }

    public function getValue(): int
    {
        $sumP = array_sum($this->positive);
        $sumN = array_sum($this->negative);
        return (int)($sumP - $sumN);
    }

    public function merge(CrdtInterface $other): static
    {
        if (!$other instanceof self) {
            throw new InvalidArgumentException('Cannot merge PNCounter with non-PNCounter CRDT.');
        }

        $otherState = $other->getState();
        $mergedP = $this->positive;
        $mergedN = $this->negative;

        foreach ($otherState['p'] as $node => $val) {
            $mergedP[$node] = max($mergedP[$node] ?? 0, (int)$val);
        }

        foreach ($otherState['n'] as $node => $val) {
            $mergedN[$node] = max($mergedN[$node] ?? 0, (int)$val);
        }

        $this->positive = $mergedP;
        $this->negative = $mergedN;

        return $this;
    }

    public function getState(): array
    {
        return [
            'type' => 'pn-counter',
            'p' => $this->positive,
            'n' => $this->negative,
            'local_node' => $this->localNodeId,
        ];
    }

    public static function fromState(array $state): static
    {
        return new static(
            positive: (array)($state['p'] ?? []),
            negative: (array)($state['n'] ?? []),
            localNodeId: (string)($state['local_node'] ?? 'local')
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'value' => $this->getValue(),
            'state' => $this->getState(),
        ];
    }
}
