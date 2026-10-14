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
 * Dot represents an atomic event/mutation identifier (nodeId, counter).
 */
class DotContext
{
    private string $nodeId;
    private int $counter;

    public function __construct(string $nodeId, int $counter)
    {
        $this->nodeId  = $nodeId;
        $this->counter = $counter;
    }

    public function getNodeId(): string
    {
        return $this->nodeId;
    }

    public function getCounter(): int
    {
        return $this->counter;
    }

    public function toString(): string
    {
        return "{$this->nodeId}:{$this->counter}";
    }

    public static function fromString(string $dot): self
    {
        $parts = explode(':', $dot, 2);
        return new self($parts[0], (int)($parts[1] ?? 0));
    }

    public function toArray(): array
    {
        return [
            'node_id' => $this->nodeId,
            'counter' => $this->counter,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self((string)$data['node_id'], (int)$data['counter']);
    }
}
