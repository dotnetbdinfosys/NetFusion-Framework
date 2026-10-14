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

class WorkflowBuilder
{
    protected string $id;
    protected string $name;
    protected int $version = 1;
    protected string $description = '';
    protected array $variablesSchema = [];
    /** @var array<string, AbstractNode> */
    protected array $nodes = [];
    /** @var array<SequenceFlow> */
    protected array $flows = [];
    protected ?string $lastNodeId = null;

    public function __construct(string $id, string $name = '', int $version = 1)
    {
        $this->id = $id;
        $this->name = $name ?: $id;
        $this->version = $version;
    }

    public static function create(string $id, string $name = '', int $version = 1): self
    {
        return new self($id, $name, $version);
    }

    public function description(string $desc): self
    {
        $this->description = $desc;
        return $this;
    }

    public function variableSchema(string $key, string $type, bool $required = false): self
    {
        $this->variablesSchema[$key] = ['type' => $type, 'required' => $required];
        return $this;
    }

    public function start(string $id = 'start', string $name = 'Start Event', bool $autoConnect = false): self
    {
        $node = new StartEvent($id, $name);
        $this->nodes[$id] = $node;
        if ($autoConnect && $this->lastNodeId !== null) {
            $this->connect($this->lastNodeId, $id);
        }
        $this->lastNodeId = $id;
        return $this;
    }

    public function end(string $id = 'end', string $name = 'End Event', bool $autoConnect = false): self
    {
        $node = new EndEvent($id, $name);
        $this->nodes[$id] = $node;
        if ($autoConnect && $this->lastNodeId !== null) {
            $this->connect($this->lastNodeId, $id);
        }
        $this->lastNodeId = $id;
        return $this;
    }

    public function serviceTask(
        string $id,
        mixed $handler,
        string $name = '',
        mixed $compensation = null,
        int $maxRetries = 0,
        int $retryDelayMs = 0,
        bool $autoConnect = false
    ): self {
        $node = new ServiceTask($id, $name ?: $id, $handler, $compensation, $maxRetries, $retryDelayMs);
        $this->nodes[$id] = $node;
        if ($autoConnect && $this->lastNodeId !== null) {
            $this->connect($this->lastNodeId, $id);
        }
        $this->lastNodeId = $id;
        return $this;
    }

    public function userTask(
        string $id,
        string $name = '',
        ?string $assignee = null,
        array $candidateGroups = [],
        array $formSchema = [],
        ?string $dueDate = null,
        bool $autoConnect = false
    ): self {
        $node = new UserTask($id, $name ?: $id, $assignee, $candidateGroups, $formSchema, $dueDate);
        $this->nodes[$id] = $node;
        if ($autoConnect && $this->lastNodeId !== null) {
            $this->connect($this->lastNodeId, $id);
        }
        $this->lastNodeId = $id;
        return $this;
    }

    public function scriptTask(
        string $id,
        mixed $script,
        string $name = '',
        bool $autoConnect = false
    ): self {
        $node = new ScriptTask($id, $name ?: $id, $script);
        $this->nodes[$id] = $node;
        if ($autoConnect && $this->lastNodeId !== null) {
            $this->connect($this->lastNodeId, $id);
        }
        $this->lastNodeId = $id;
        return $this;
    }

    public function exclusiveGateway(
        string $id,
        string $name = 'Exclusive Gateway',
        ?string $defaultFlowId = null,
        bool $autoConnect = false
    ): self {
        $node = new ExclusiveGateway($id, $name, $defaultFlowId);
        $this->nodes[$id] = $node;
        if ($autoConnect && $this->lastNodeId !== null) {
            $this->connect($this->lastNodeId, $id);
        }
        $this->lastNodeId = $id;
        return $this;
    }

    public function parallelGateway(
        string $id,
        string $name = 'Parallel Gateway',
        bool $autoConnect = false
    ): self {
        $node = new ParallelGateway($id, $name);
        $this->nodes[$id] = $node;
        if ($autoConnect && $this->lastNodeId !== null) {
            $this->connect($this->lastNodeId, $id);
        }
        $this->lastNodeId = $id;
        return $this;
    }

    public function timerCatchEvent(
        string $id,
        int $durationSeconds,
        string $name = 'Timer Event',
        bool $autoConnect = false
    ): self {
        $node = new TimerCatchEvent($id, $name, $durationSeconds);
        $this->nodes[$id] = $node;
        if ($autoConnect && $this->lastNodeId !== null) {
            $this->connect($this->lastNodeId, $id);
        }
        $this->lastNodeId = $id;
        return $this;
    }

    public function errorBoundaryEvent(
        string $id,
        string $attachedToNodeId,
        ?string $errorCode = null,
        string $name = 'Error Boundary'
    ): self {
        $node = new ErrorBoundaryEvent($id, $name, $attachedToNodeId, $errorCode);
        $this->nodes[$id] = $node;
        $this->lastNodeId = $id;
        return $this;
    }

    public function connect(
        string $sourceRef,
        string $targetRef,
        ?string $condition = null,
        ?string $name = null,
        bool $isDefault = false,
        ?string $flowId = null
    ): self {
        $id = $flowId ?: 'flow_' . $sourceRef . '_to_' . $targetRef . ($condition ? '_' . substr(md5($condition), 0, 4) : '');
        $this->flows[] = new SequenceFlow($id, $sourceRef, $targetRef, $condition, $name, $isDefault);
        return $this;
    }

    public function build(): WorkflowDefinition
    {
        $definition = new WorkflowDefinition(
            $this->id,
            $this->name,
            $this->version,
            $this->description,
            $this->variablesSchema
        );

        foreach ($this->nodes as $node) {
            $definition->addNode($node);
        }

        foreach ($this->flows as $flow) {
            $definition->addSequenceFlow($flow);
        }

        return $definition;
    }
}
