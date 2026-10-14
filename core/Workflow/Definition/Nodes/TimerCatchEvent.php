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

namespace NetFusion\Core\Workflow\Definition\Nodes;

class TimerCatchEvent extends AbstractNode
{
    protected int $durationSeconds;

    public function __construct(
        string $id,
        string $name,
        int $durationSeconds,
        array $metadata = []
    ) {
        parent::__construct($id, $name, 'timerCatchEvent', $metadata);
        $this->durationSeconds = $durationSeconds;
    }

    public function getDurationSeconds(): int
    {
        return $this->durationSeconds;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'durationSeconds' => $this->durationSeconds,
            'incomingFlows' => $this->incomingFlows,
            'outgoingFlows' => $this->outgoingFlows,
            'metadata' => $this->metadata,
        ];
    }

    public static function fromArray(array $data): self
    {
        $node = new self(
            $data['id'],
            $data['name'] ?? 'Timer Event',
            (int) ($data['durationSeconds'] ?? 0),
            $data['metadata'] ?? []
        );
        $node->incomingFlows = $data['incomingFlows'] ?? [];
        $node->outgoingFlows = $data['outgoingFlows'] ?? [];
        return $node;
    }
}
