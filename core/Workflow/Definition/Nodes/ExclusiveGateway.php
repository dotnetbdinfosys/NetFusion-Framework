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

class ExclusiveGateway extends AbstractNode
{
    protected ?string $defaultFlowId;

    public function __construct(
        string $id,
        string $name = 'Exclusive Gateway',
        ?string $defaultFlowId = null,
        array $metadata = []
    ) {
        parent::__construct($id, $name, 'exclusiveGateway', $metadata);
        $this->defaultFlowId = $defaultFlowId;
    }

    public function getDefaultFlowId(): ?string
    {
        return $this->defaultFlowId;
    }

    public function setDefaultFlowId(?string $flowId): self
    {
        $this->defaultFlowId = $flowId;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'defaultFlowId' => $this->defaultFlowId,
            'incomingFlows' => $this->incomingFlows,
            'outgoingFlows' => $this->outgoingFlows,
            'metadata' => $this->metadata,
        ];
    }

    public static function fromArray(array $data): self
    {
        $node = new self(
            $data['id'],
            $data['name'] ?? 'Exclusive Gateway',
            $data['defaultFlowId'] ?? null,
            $data['metadata'] ?? []
        );
        $node->incomingFlows = $data['incomingFlows'] ?? [];
        $node->outgoingFlows = $data['outgoingFlows'] ?? [];
        return $node;
    }
}
