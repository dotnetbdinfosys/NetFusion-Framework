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

namespace NetFusion\Core\Scheduling\DAG;

use NetFusion\Core\Scheduling\Models\DAGGraph;
use NetFusion\Core\Scheduling\Models\DAGNode;

class DAGBuilder
{
    private DAGGraph $graph;
    private ?string $lastAddedId = null;

    public function __construct(string $name = 'dag_workflow')
    {
        $this->graph = new DAGGraph($name);
    }

    public static function create(string $name = 'dag_workflow'): self
    {
        return new self($name);
    }

    /**
     * Add a node to the DAG
     */
    public function task(string $id, mixed $callback, array $dependencies = [], ?string $name = null): self
    {
        $nodeName = $name ?? $id;
        $node = new DAGNode($id, $nodeName, $callback, $dependencies);
        $this->graph->addNode($node);
        $this->lastAddedId = $id;
        return $this;
    }

    /**
     * Fluent chain: add a task that depends on the previously added task(s)
     */
    public function then(string|array $taskId, mixed $callback = null, ?string $name = null): self
    {
        $upstream = $this->lastAddedId ? [$this->lastAddedId] : [];

        if (is_array($taskId)) {
            // Batch of tasks that depend on previous task
            foreach ($taskId as $id => $cb) {
                if (is_int($id) && is_string($cb)) {
                    // Just an ID referencing another or empty closure
                    $this->graph->addDependency($cb, $this->lastAddedId);
                } else {
                    $this->task($id, $cb, $upstream, $name);
                }
            }
        } else {
            $this->task($taskId, $callback, $upstream, $name);
        }

        return $this;
    }

    /**
     * Set retry policy on the last added node
     */
    public function retry(int $maxAttempts, int $delaySeconds = 0, bool $exponential = false, float $multiplier = 2.0): self
    {
        if ($this->lastAddedId !== null) {
            $node = $this->graph->getNode($this->lastAddedId);
            if ($node) {
                $node->retry($maxAttempts, $delaySeconds, $exponential, $multiplier);
            }
        }
        return $this;
    }

    /**
     * Set conditional execution on the last added node
     */
    public function when(callable $condition): self
    {
        if ($this->lastAddedId !== null) {
            $node = $this->graph->getNode($this->lastAddedId);
            if ($node) {
                $node->when($condition);
            }
        }
        return $this;
    }

    public function build(): DAGGraph
    {
        $this->graph->validate();
        return $this->graph;
    }
}
