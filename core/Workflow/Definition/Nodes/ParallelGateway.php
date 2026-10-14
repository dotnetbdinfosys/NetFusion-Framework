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

class ParallelGateway extends AbstractNode
{
    public function __construct(
        string $id,
        string $name = 'Parallel Gateway',
        array $metadata = []
    ) {
        parent::__construct($id, $name, 'parallelGateway', $metadata);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'incomingFlows' => $this->incomingFlows,
            'outgoingFlows' => $this->outgoingFlows,
            'metadata' => $this->metadata,
        ];
    }

    public static function fromArray(array $data): self
    {
        $node = new self(
            $data['id'],
            $data['name'] ?? 'Parallel Gateway',
            $data['metadata'] ?? []
        );
        $node->incomingFlows = $data['incomingFlows'] ?? [];
        $node->outgoingFlows = $data['outgoingFlows'] ?? [];
        return $node;
    }
}
