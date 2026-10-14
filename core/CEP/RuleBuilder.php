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

namespace NetFusion\Core\CEP;

use NetFusion\Core\CEP\Contracts\CEPRuleInterface;
use NetFusion\Core\CEP\Contracts\SinkInterface;
use NetFusion\Core\CEP\Contracts\WindowAssignerInterface;
use NetFusion\Core\CEP\Contracts\WindowFunctionInterface;
use NetFusion\Core\CEP\Pattern\Pattern;
use NetFusion\Core\CEP\Stream\Sink\WorkflowStreamSink;
use NetFusion\Core\CEP\Window\TumblingTimeWindow;
use NetFusion\Core\CEP\Window\WindowAggregate;
use NetFusion\Core\Workflow\WorkflowEngine;

class RuleBuilder
{
    protected string $name;
    protected string $streamName = 'default';
    protected string $description = '';
    protected bool $enabled = true;
    /** @var callable|null */
    protected $filter = null;
    /** @var string|callable|null */
    protected $keySelector = null;
    protected ?Pattern $pattern = null;
    protected ?WindowAssignerInterface $windowAssigner = null;
    protected ?WindowFunctionInterface $windowFunction = null;
    /** @var callable|null */
    protected $having = null;
    /** @var array<SinkInterface|callable> */
    protected array $actions = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public static function create(string $name): self
    {
        return new self($name);
    }

    public function description(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function from(string $streamName): self
    {
        $this->streamName = $streamName;
        return $this;
    }

    public function where(callable $filter): self
    {
        $this->filter = $filter;
        return $this;
    }

    public function keyBy(string|callable $keySelector): self
    {
        $this->keySelector = $keySelector;
        return $this;
    }

    public function pattern(Pattern $pattern): self
    {
        $this->pattern = $pattern;
        return $this;
    }

    public function window(WindowAssignerInterface $assigner): self
    {
        $this->windowAssigner = $assigner;
        return $this;
    }

    public function aggregate(WindowFunctionInterface|callable $function): self
    {
        $this->windowFunction = $function instanceof WindowFunctionInterface
            ? $function
            : WindowAggregate::custom($function);
        return $this;
    }

    public function sum(string $field): self
    {
        $this->windowFunction = WindowAggregate::sum($field);
        return $this;
    }

    public function avg(string $field): self
    {
        $this->windowFunction = WindowAggregate::avg($field);
        return $this;
    }

    public function count(): self
    {
        $this->windowFunction = WindowAggregate::count();
        return $this;
    }

    public function min(string $field): self
    {
        $this->windowFunction = WindowAggregate::min($field);
        return $this;
    }

    public function max(string $field): self
    {
        $this->windowFunction = WindowAggregate::max($field);
        return $this;
    }

    public function having(callable $condition): self
    {
        $this->having = $condition;
        return $this;
    }

    public function then(SinkInterface|callable $action): self
    {
        $this->actions[] = $action;
        return $this;
    }

    public function toWorkflow(
        string $workflowDefinitionId,
        ?WorkflowEngine $engine = null,
        ?callable $payloadMapper = null
    ): self {
        $this->actions[] = new WorkflowStreamSink(
            'workflow_' . $workflowDefinitionId,
            $workflowDefinitionId,
            $engine,
            $payloadMapper
        );
        return $this;
    }

    public function enabled(bool $enabled = true): self
    {
        $this->enabled = $enabled;
        return $this;
    }

    public function build(): CEPRule
    {
        $rule = new CEPRule($this->name, $this->streamName, $this->description, $this->enabled);

        if ($this->filter !== null) {
            $rule->setFilter($this->filter);
        }

        if ($this->keySelector !== null) {
            $rule->setKeySelector($this->keySelector);
        }

        if ($this->pattern !== null) {
            $rule->setPattern($this->pattern);
        }

        if ($this->windowAssigner !== null) {
            $rule->setWindow($this->windowAssigner, $this->windowFunction ?? WindowAggregate::count(), $this->having);
        }

        foreach ($this->actions as $action) {
            $rule->addAction($action);
        }

        return $rule;
    }
}
