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

use NetFusion\Core\Scheduling\Contracts\DAGRunnerInterface;
use NetFusion\Core\Scheduling\Models\DAGGraph;
use NetFusion\Core\Scheduling\Models\DAGNode;
use NetFusion\Core\Scheduling\Exceptions\CycleDetectedException;
use NetFusion\Core\Scheduling\Exceptions\TaskExecutionException;

class DAGExecutor implements DAGRunnerInterface
{
    private TopologicalSorter $sorter;

    public function __construct(?TopologicalSorter $sorter = null)
    {
        $this->sorter = $sorter ?? new TopologicalSorter();
    }

    /**
     * Run the DAG workflow
     *
     * @param DAGGraph $dag
     * @param array $initialContext
     * @return array
     */
    public function run(DAGGraph $dag, array $initialContext = []): array
    {
        $dag->validate();
        $stages = $this->sorter->getStages($dag);
        $context = $initialContext;
        $startTime = microtime(true);
        $allSuccessful = true;
        $hasFailures = false;

        $nodes = $dag->getNodes();

        foreach ($stages as $stageIndex => $nodeIds) {
            foreach ($nodeIds as $nodeId) {
                $node = $nodes[$nodeId];

                // Check if any upstream dependencies failed or were skipped
                $dependencyFailed = false;
                foreach ($node->getDependencies() as $depId) {
                    $depNode = $nodes[$depId] ?? null;
                    if ($depNode && ($depNode->isFailed() || $depNode->isSkipped())) {
                        $dependencyFailed = true;
                        break;
                    }
                }

                if ($dependencyFailed) {
                    $node->setState(DAGNode::STATE_SKIPPED);
                    $node->setError("Skipped due to upstream dependency failure or skip.");
                    continue;
                }

                // Check conditional execution
                $condition = $node->getCondition();
                if ($condition !== null && is_callable($condition)) {
                    $shouldRun = call_user_func($condition, $context);
                    if (!$shouldRun) {
                        $node->setState(DAGNode::STATE_SKIPPED);
                        continue;
                    }
                }

                // Execute node with retry
                $this->executeNode($node, $context);

                if ($node->isCompleted()) {
                    $context[$node->getId()] = $node->getResult();
                } elseif ($node->isFailed()) {
                    $hasFailures = true;
                    $allSuccessful = false;
                }
            }
        }

        $totalDuration = microtime(true) - $startTime;
        $status = $hasFailures ? 'FAILED' : ($allSuccessful ? 'SUCCESS' : 'PARTIAL');

        $nodeReports = [];
        foreach ($nodes as $id => $node) {
            $nodeReports[$id] = [
                'id' => $node->getId(),
                'name' => $node->getName(),
                'state' => $node->getState(),
                'result' => $node->getResult(),
                'error' => $node->getError(),
                'duration' => $node->getDuration(),
            ];
        }

        return [
            'dag_name' => $dag->getName(),
            'status' => $status,
            'duration' => $totalDuration,
            'context' => $context,
            'nodes' => $nodeReports,
        ];
    }

    private function executeNode(DAGNode $node, array $context): void
    {
        $node->setState(DAGNode::STATE_RUNNING);
        $nodeStartTime = microtime(true);

        $retryPolicy = $node->getRetryPolicy();
        $maxAttempts = $retryPolicy ? $retryPolicy->getMaxAttempts() : 1;
        $attempts = 0;
        $lastException = null;

        while ($attempts < $maxAttempts) {
            $attempts++;
            try {
                $callback = $node->getCallback();
                $result = null;

                if (is_callable($callback)) {
                    $result = call_user_func($callback, $context);
                } elseif (is_string($callback) && class_exists($callback)) {
                    $instance = new $callback();
                    if (method_exists($instance, 'handle')) {
                        $result = $instance->handle($context);
                    } elseif (is_callable($instance)) {
                        $result = $instance($context);
                    }
                }

                $node->setResult($result);
                $node->setState(DAGNode::STATE_COMPLETED);
                $node->setDuration(microtime(true) - $nodeStartTime);
                return;
            } catch (\Throwable $e) {
                $lastException = $e;
                if ($attempts < $maxAttempts && $retryPolicy) {
                    $delay = $retryPolicy->getDelayForAttempt($attempts);
                    if ($delay > 0) {
                        usleep((int)($delay * 1_000_000));
                    }
                }
            }
        }

        $node->setState(DAGNode::STATE_FAILED);
        $node->setError($lastException ? $lastException->getMessage() : 'Execution failed');
        $node->setDuration(microtime(true) - $nodeStartTime);
    }
}
