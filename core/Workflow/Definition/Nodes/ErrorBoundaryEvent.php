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

class ErrorBoundaryEvent extends AbstractNode
{
    protected ?string $attachedToNodeId;
    protected ?string $errorCode;

    public function __construct(
        string $id,
        string $name,
        ?string $attachedToNodeId = null,
        ?string $errorCode = null,
        array $metadata = []
    ) {
        parent::__construct($id, $name, 'errorBoundaryEvent', $metadata);
        $this->attachedToNodeId = $attachedToNodeId;
        $this->errorCode = $errorCode;
    }

    public function getAttachedToNodeId(): ?string
    {
        return $this->attachedToNodeId;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'attachedToNodeId' => $this->attachedToNodeId,
            'errorCode' => $this->errorCode,
            'incomingFlows' => $this->incomingFlows,
            'outgoingFlows' => $this->outgoingFlows,
            'metadata' => $this->metadata,
        ];
    }

    public static function fromArray(array $data): self
    {
        $node = new self(
            $data['id'],
            $data['name'] ?? 'Error Boundary Event',
            $data['attachedToNodeId'] ?? null,
            $data['errorCode'] ?? null,
            $data['metadata'] ?? []
        );
        $node->incomingFlows = $data['incomingFlows'] ?? [];
        $node->outgoingFlows = $data['outgoingFlows'] ?? [];
        return $node;
    }
}
