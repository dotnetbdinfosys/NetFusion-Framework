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

namespace NetFusion\Core\Workflow\Execution\Saga;

use NetFusion\Core\Application;
use NetFusion\Core\Workflow\Contracts\TaskHandlerInterface;
use NetFusion\Core\Workflow\Execution\ExecutionContext;
use NetFusion\Core\Workflow\Execution\ProcessInstance;
use Throwable;

class SagaCoordinator
{
    /**
     * Compensate all registered steps for an instance in LIFO order.
     *
     * @param ProcessInstance $instance
     * @param Throwable|null $rootCause
     * @return SagaExecutionResult
     */
    public function compensate(ProcessInstance $instance, ?Throwable $rootCause = null): SagaExecutionResult
    {
        $stack = $instance->getCompensationStack();
        $compensated = [];
        $failed = [];
        $errors = [];

        // LIFO order: reverse array
        $stepsToCompensate = array_reverse($stack);

        foreach ($stepsToCompensate as $step) {
            $nodeId = $step->getNodeId();
            $handler = $step->getHandler();
            $input = $step->getInputSnapshot();

            try {
                $context = new ExecutionContext($instance, $nodeId, $input);
                $this->executeCompensationHandler($handler, $context);
                $compensated[] = $nodeId;
            } catch (Throwable $e) {
                $failed[] = $nodeId;
                $errors[$nodeId] = $e->getMessage();
            }
        }

        $allSuccess = count($failed) === 0;
        return new SagaExecutionResult($allSuccess, $compensated, $failed, $errors);
    }

    /**
     * Execute compensation handler.
     */
    protected function executeCompensationHandler(mixed $handler, ExecutionContext $context): void
    {
        if (is_callable($handler)) {
            $handler($context);
            return;
        }

        if (is_string($handler)) {
            if (class_exists($handler)) {
                $instance = null;
                if (class_exists(Application::class) && Application::getInstance()) {
                    $instance = Application::getInstance()->make($handler);
                } else {
                    $instance = new $handler();
                }

                if (method_exists($instance, 'compensate')) {
                    $instance->compensate($context);
                    return;
                }

                if ($instance instanceof TaskHandlerInterface) {
                    $instance->execute($context);
                    return;
                }

                if (is_callable($instance)) {
                    $instance($context);
                    return;
                }
            }
        }
    }
}
