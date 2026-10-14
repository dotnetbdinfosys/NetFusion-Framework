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

class ScriptTask extends AbstractNode
{
    /** @var callable|string */
    protected mixed $script;

    public function __construct(
        string $id,
        string $name,
        mixed $script,
        array $metadata = []
    ) {
        parent::__construct($id, $name, 'scriptTask', $metadata);
        $this->script = $script;
    }

    public function getScript(): mixed
    {
        return $this->script;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'script' => is_string($this->script) ? $this->script : 'Closure',
            'incomingFlows' => $this->incomingFlows,
            'outgoingFlows' => $this->outgoingFlows,
            'metadata' => $this->metadata,
        ];
    }

    public static function fromArray(array $data): self
    {
        $node = new self(
            $data['id'],
            $data['name'] ?? 'Script Task',
            $data['script'] ?? '',
            $data['metadata'] ?? []
        );
        $node->incomingFlows = $data['incomingFlows'] ?? [];
        $node->outgoingFlows = $data['outgoingFlows'] ?? [];
        return $node;
    }
}
