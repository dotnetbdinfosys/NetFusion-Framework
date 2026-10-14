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

class LWWRegister implements CrdtInterface
{
    public function __construct(
        protected mixed $value = null,
        protected float $timestamp = 0.0,
        protected string $nodeId = 'local'
    ) {
        if ($this->timestamp === 0.0) {
            $this->timestamp = microtime(true);
        }
    }

    public function set(mixed $value, ?float $timestamp = null, ?string $nodeId = null): self
    {
        $this->value = $value;
        $this->timestamp = $timestamp ?? microtime(true);
        if ($nodeId !== null) {
            $this->nodeId = $nodeId;
        }
        return $this;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function getTimestamp(): float
    {
        return $this->timestamp;
    }

    public function getNodeId(): string
    {
        return $this->nodeId;
    }

    public function merge(CrdtInterface $other): static
    {
        if (!$other instanceof self) {
            throw new InvalidArgumentException('Cannot merge LWWRegister with non-LWWRegister CRDT.');
        }

        $otherState = $other->getState();
        $otherTs = (float)$otherState['timestamp'];
        $otherNode = (string)$otherState['node_id'];
        $otherVal = $otherState['value'];

        if ($otherTs > $this->timestamp) {
            $this->value = $otherVal;
            $this->timestamp = $otherTs;
            $this->nodeId = $otherNode;
        } elseif ($otherTs === $this->timestamp) {
            // Deterministic tie-breaker
            if (strcmp($otherNode, $this->nodeId) > 0) {
                $this->value = $otherVal;
                $this->timestamp = $otherTs;
                $this->nodeId = $otherNode;
            }
        }

        return $this;
    }

    public function getState(): array
    {
        return [
            'type' => 'lww-register',
            'value' => $this->value,
            'timestamp' => $this->timestamp,
            'node_id' => $this->nodeId,
        ];
    }

    public static function fromState(array $state): static
    {
        return new static(
            value: $state['value'] ?? null,
            timestamp: (float)($state['timestamp'] ?? 0.0),
            nodeId: (string)($state['node_id'] ?? 'local')
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
