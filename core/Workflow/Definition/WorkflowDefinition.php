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

namespace NetFusion\Core\Workflow\Definition;

use NetFusion\Core\Workflow\Definition\Nodes\AbstractNode;
use NetFusion\Core\Workflow\Definition\Nodes\StartEvent;
use NetFusion\Core\Workflow\Definition\Nodes\EndEvent;
use NetFusion\Core\Workflow\Definition\Nodes\ServiceTask;
use NetFusion\Core\Workflow\Definition\Nodes\UserTask;
use NetFusion\Core\Workflow\Definition\Nodes\ScriptTask;
use NetFusion\Core\Workflow\Definition\Nodes\ExclusiveGateway;
use NetFusion\Core\Workflow\Definition\Nodes\ParallelGateway;
use NetFusion\Core\Workflow\Definition\Nodes\TimerCatchEvent;
use NetFusion\Core\Workflow\Definition\Nodes\ErrorBoundaryEvent;
use InvalidArgumentException;

class WorkflowDefinition
{
    protected string $id;
    protected string $name;
    protected int $version;
    protected string $description;
    /** @var array<string, AbstractNode> */
    protected array $nodes = [];
    /** @var array<string, SequenceFlow> */
    protected array $sequenceFlows = [];
    /** @var array<string, mixed> */
    protected array $variablesSchema = [];
    protected bool $isExecutable = true;

    public function __construct(
        string $id,
        string $name,
        int $version = 1,
        string $description = '',
        array $variablesSchema = []
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->version = $version;
        $this->description = $description;
        $this->variablesSchema = $variablesSchema;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getVariablesSchema(): array
    {
        return $this->variablesSchema;
    }

    public function isExecutable(): bool
    {
        return $this->isExecutable;
    }

    public function setExecutable(bool $executable): self
    {
        $this->isExecutable = $executable;
        return $this;
    }

    public function addNode(AbstractNode $node): self
    {
        $this->nodes[$node->getId()] = $node;
        return $this;
    }

    public function getNode(string $nodeId): ?AbstractNode
    {
        return $this->nodes[$nodeId] ?? null;
    }

    /**
     * @return array<string, AbstractNode>
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }

    public function addSequenceFlow(SequenceFlow $flow): self
    {
        $this->sequenceFlows[$flow->getId()] = $flow;

        // Wire node incoming & outgoing
        if (isset($this->nodes[$flow->getSourceRef()])) {
            $this->nodes[$flow->getSourceRef()]->addOutgoingFlow($flow->getId());
        }
        if (isset($this->nodes[$flow->getTargetRef()])) {
            $this->nodes[$flow->getTargetRef()]->addIncomingFlow($flow->getId());
        }

        return $this;
    }

    public function getSequenceFlow(string $flowId): ?SequenceFlow
    {
        return $this->sequenceFlows[$flowId] ?? null;
    }

    /**
     * @return array<string, SequenceFlow>
     */
    public function getSequenceFlows(): array
    {
        return $this->sequenceFlows;
    }

    /**
     * Get outgoing sequence flows for a specific node.
     *
     * @param string $nodeId
     * @return array<SequenceFlow>
     */
    public function getOutgoingFlowsForNode(string $nodeId): array
    {
        $flows = [];
        $node = $this->getNode($nodeId);
        if ($node) {
            foreach ($node->getOutgoingFlows() as $flowId) {
                if (isset($this->sequenceFlows[$flowId])) {
                    $flows[] = $this->sequenceFlows[$flowId];
                }
            }
        }
        return $flows;
    }

    /**
     * Get incoming sequence flows for a specific node.
     *
     * @param string $nodeId
     * @return array<SequenceFlow>
     */
    public function getIncomingFlowsForNode(string $nodeId): array
    {
        $flows = [];
        $node = $this->getNode($nodeId);
        if ($node) {
            foreach ($node->getIncomingFlows() as $flowId) {
                if (isset($this->sequenceFlows[$flowId])) {
                    $flows[] = $this->sequenceFlows[$flowId];
                }
            }
        }
        return $flows;
    }

    /**
     * Get all start event nodes.
     *
     * @return array<StartEvent>
     */
    public function getStartNodes(): array
    {
        $startNodes = [];
        foreach ($this->nodes as $node) {
            if ($node instanceof StartEvent) {
                $startNodes[] = $node;
            }
        }
        return $startNodes;
    }

    public function toArray(): array
    {
        $nodesArray = [];
        foreach ($this->nodes as $id => $node) {
            $nodesArray[$id] = $node->toArray();
        }

        $flowsArray = [];
        foreach ($this->sequenceFlows as $id => $flow) {
            $flowsArray[$id] = $flow->toArray();
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'version' => $this->version,
            'description' => $this->description,
            'isExecutable' => $this->isExecutable,
            'variablesSchema' => $this->variablesSchema,
            'nodes' => $nodesArray,
            'sequenceFlows' => $flowsArray,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public static function fromArray(array $data): self
    {
        $definition = new self(
            $data['id'],
            $data['name'] ?? 'Workflow',
            (int) ($data['version'] ?? 1),
            $data['description'] ?? '',
            $data['variablesSchema'] ?? []
        );

        $definition->setExecutable((bool) ($data['isExecutable'] ?? true));

        // Hydrate nodes
        foreach ($data['nodes'] ?? [] as $nodeData) {
            $type = $nodeData['type'] ?? 'serviceTask';
            $node = match ($type) {
                'startEvent' => StartEvent::fromArray($nodeData),
                'endEvent' => EndEvent::fromArray($nodeData),
                'serviceTask' => ServiceTask::fromArray($nodeData),
                'userTask' => UserTask::fromArray($nodeData),
                'scriptTask' => ScriptTask::fromArray($nodeData),
                'exclusiveGateway' => ExclusiveGateway::fromArray($nodeData),
                'parallelGateway' => ParallelGateway::fromArray($nodeData),
                'timerCatchEvent' => TimerCatchEvent::fromArray($nodeData),
                'errorBoundaryEvent' => ErrorBoundaryEvent::fromArray($nodeData),
                default => throw new InvalidArgumentException("Unknown node type: {$type}")
            };
            $definition->addNode($node);
        }

        // Hydrate sequence flows
        foreach ($data['sequenceFlows'] ?? [] as $flowData) {
            $flow = SequenceFlow::fromArray($flowData);
            $definition->addSequenceFlow($flow);
        }

        return $definition;
    }

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new InvalidArgumentException('Invalid JSON provided for WorkflowDefinition');
        }
        return self::fromArray($data);
    }
}
