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

namespace NetFusion\Core\Workflow;

use NetFusion\Core\Application;
use NetFusion\Core\Workflow\Contracts\TaskHandlerInterface;
use NetFusion\Core\Workflow\Contracts\WorkflowEngineInterface;
use NetFusion\Core\Workflow\Contracts\WorkflowInstanceInterface;
use NetFusion\Core\Workflow\Contracts\Store\WorkflowStateStoreInterface;
use NetFusion\Core\Workflow\Definition\WorkflowDefinition;
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
use NetFusion\Core\Workflow\Execution\ActivityExecution;
use NetFusion\Core\Workflow\Execution\ExecutionContext;
use NetFusion\Core\Workflow\Execution\ProcessInstance;
use NetFusion\Core\Workflow\Execution\Token;
use NetFusion\Core\Workflow\Execution\Saga\CompensationStep;
use NetFusion\Core\Workflow\Execution\Saga\SagaCoordinator;
use NetFusion\Core\Workflow\Exceptions\WorkflowException;
use NetFusion\Core\Workflow\Exceptions\NodeExecutionException;
use NetFusion\Core\Workflow\Store\ArrayWorkflowStore;
use Throwable;

class WorkflowEngine implements WorkflowEngineInterface
{
    protected WorkflowStateStoreInterface $store;
    protected SagaCoordinator $sagaCoordinator;
    /** @var array<string, WorkflowDefinition> */
    protected array $registry = [];

    public function __construct(?WorkflowStateStoreInterface $store = null, ?SagaCoordinator $sagaCoordinator = null)
    {
        $this->store = $store ?? new ArrayWorkflowStore();
        $this->sagaCoordinator = $sagaCoordinator ?? new SagaCoordinator();
    }

    public function getStore(): WorkflowStateStoreInterface
    {
        return $this->store;
    }

    public function getSagaCoordinator(): SagaCoordinator
    {
        return $this->sagaCoordinator;
    }

    public function register(WorkflowDefinition $definition): self
    {
        $this->registry[$definition->getId()] = $definition;
        $this->store->saveDefinition($definition);
        return $this;
    }

    public function getDefinition(string $definitionId): ?WorkflowDefinition
    {
        return $this->registry[$definitionId] ?? $this->store->loadDefinition($definitionId);
    }

    public function start(string $definitionId, array $variables = [], ?string $businessKey = null): ProcessInstance
    {
        $definition = $this->getDefinition($definitionId);
        if (!$definition) {
            throw new WorkflowException("Workflow definition [{$definitionId}] not found in engine registry or store.");
        }

        $instanceId = 'wfi_' . bin2hex(random_bytes(8));
        $instance = new ProcessInstance(
            $instanceId,
            $definitionId,
            $businessKey,
            WorkflowInstanceInterface::STATUS_PENDING,
            $variables
        );

        $startNodes = $definition->getStartNodes();
        if (empty($startNodes)) {
            throw new WorkflowException("Workflow definition [{$definitionId}] has no start events.");
        }

        foreach ($startNodes as $startNode) {
            $token = new Token(uniqid('tok_', true), $startNode->getId(), Token::STATE_ACTIVE);
            $instance->addToken($token);
        }

        $instance->setStatus(WorkflowInstanceInterface::STATUS_RUNNING);
        $this->store->saveInstance($instance);

        return $this->execute($instance, $definition);
    }

    public function signal(string $instanceId, string $activityId, array $payload = []): ProcessInstance
    {
        $instance = $this->getInstance($instanceId);
        if (!$instance) {
            throw new WorkflowException("Workflow instance [{$instanceId}] not found.");
        }

        $definition = $this->getDefinition($instance->getDefinitionId());
        if (!$definition) {
            throw new WorkflowException("Workflow definition [{$instance->getDefinitionId()}] not found.");
        }

        if (!empty($payload)) {
            $instance->mergeVariables($payload);
        }

        // Find waiting token for this activity/node
        $waitingTokenFound = false;
        foreach ($instance->getTokens() as $token) {
            if ($token->getNodeId() === $activityId && $token->isWaiting()) {
                $token->setState(Token::STATE_ACTIVE);
                $waitingTokenFound = true;
                break;
            }
        }

        if (!$waitingTokenFound) {
            // Check if any token is waiting
            foreach ($instance->getTokens() as $token) {
                if ($token->isWaiting()) {
                    $token->setState(Token::STATE_ACTIVE);
                    $waitingTokenFound = true;
                    break;
                }
            }
        }

        $instance->setStatus(WorkflowInstanceInterface::STATUS_RUNNING);
        $this->store->saveInstance($instance);

        // Continue execution from next node of the signaled activity
        return $this->resumeFromActivity($instance, $definition, $activityId);
    }

    public function completeUserTask(string $instanceId, string $taskId, array $data = [], ?string $completedBy = null): ProcessInstance
    {
        $this->store->saveUserTask([
            'id' => 'utask_' . $taskId . '_' . $instanceId,
            'instanceId' => $instanceId,
            'nodeId' => $taskId,
            'assignee' => $completedBy,
            'status' => 'COMPLETED',
            'formData' => $data,
            'resolvedAt' => date('Y-m-d H:i:s'),
        ]);

        return $this->signal($instanceId, $taskId, $data);
    }

    public function getInstance(string $instanceId): ?ProcessInstance
    {
        return $this->store->loadInstance($instanceId);
    }

    public function cancel(string $instanceId, string $reason = 'Cancelled by user'): ProcessInstance
    {
        $instance = $this->getInstance($instanceId);
        if (!$instance) {
            throw new WorkflowException("Workflow instance [{$instanceId}] not found.");
        }

        foreach ($instance->getTokens() as $token) {
            $token->setState(Token::STATE_CANCELLED);
        }

        $instance->setStatus(WorkflowInstanceInterface::STATUS_CANCELLED);
        $this->store->saveInstance($instance);

        return $instance;
    }

    /**
     * Resumes execution after a waiting activity was signaled.
     */
    protected function resumeFromActivity(ProcessInstance $instance, WorkflowDefinition $definition, string $activityId): ProcessInstance
    {
        $node = $definition->getNode($activityId);
        if ($node) {
            // Find active token on this node
            $activeToken = null;
            foreach ($instance->getTokens() as $token) {
                if ($token->getNodeId() === $activityId && $token->isActive()) {
                    $activeToken = $token;
                    break;
                }
            }

            if ($activeToken) {
                // Advance outgoing flows from this node
                $this->advanceToken($instance, $definition, $activeToken, $node);
            }
        }

        return $this->execute($instance, $definition);
    }

    /**
     * Main execution cycle for active tokens in the process instance.
     */
    public function execute(ProcessInstance $instance, ?WorkflowDefinition $definition = null): ProcessInstance
    {
        if ($definition === null) {
            $definition = $this->getDefinition($instance->getDefinitionId());
            if (!$definition) {
                throw new WorkflowException("Workflow definition [{$instance->getDefinitionId()}] not found.");
            }
        }

        $iterationGuard = 0;
        $maxIterations = 1000;

        while (count($instance->getActiveTokens()) > 0) {
            $iterationGuard++;
            if ($iterationGuard > $maxIterations) {
                throw new WorkflowException("Maximum execution loop iterations ({$maxIterations}) exceeded. Possible infinite loop in workflow graph.");
            }

            $activeTokens = $instance->getActiveTokens();
            $token = $activeTokens[0];
            $nodeId = $token->getNodeId();
            $node = $definition->getNode($nodeId);

            if (!$node) {
                throw new WorkflowException("Node [{$nodeId}] not found in workflow definition [{$definition->getId()}].");
            }

            $startTime = microtime(true);

            // Execute node according to type
            try {
                if ($node instanceof StartEvent) {
                    $this->advanceToken($instance, $definition, $token, $node);
                    $this->logActivity($instance, $node, 'START', [], [], ActivityExecution::STATUS_SUCCESS, $startTime);
                } elseif ($node instanceof EndEvent) {
                    $token->setState(Token::STATE_COMPLETED);
                    $instance->removeToken($token->getId());
                    $this->logActivity($instance, $node, 'END', [], [], ActivityExecution::STATUS_SUCCESS, $startTime);

                    if (count($instance->getActiveTokens()) === 0 && count($instance->getWaitingTokens()) === 0) {
                        $instance->setStatus(WorkflowInstanceInterface::STATUS_COMPLETED);
                    }
                } elseif ($node instanceof ServiceTask) {
                    $this->executeServiceTask($instance, $definition, $token, $node, $startTime);
                } elseif ($node instanceof UserTask) {
                    $token->setState(Token::STATE_WAITING);
                    $instance->setStatus(WorkflowInstanceInterface::STATUS_SUSPENDED);
                    $this->store->saveUserTask([
                        'id' => 'utask_' . $node->getId() . '_' . $instance->getId(),
                        'instanceId' => $instance->getId(),
                        'nodeId' => $node->getId(),
                        'assignee' => $node->getAssignee(),
                        'status' => 'PENDING',
                        'formData' => $node->getFormSchema(),
                        'createdAt' => date('Y-m-d H:i:s'),
                    ]);
                    $this->logActivity($instance, $node, 'USER_TASK_WAIT', [], [], ActivityExecution::STATUS_SUSPENDED, $startTime);
                    break; // Stop execution loop until human interaction/signal
                } elseif ($node instanceof ScriptTask) {
                    $this->executeScriptTask($instance, $definition, $token, $node, $startTime);
                } elseif ($node instanceof ExclusiveGateway) {
                    $this->executeExclusiveGateway($instance, $definition, $token, $node, $startTime);
                } elseif ($node instanceof ParallelGateway) {
                    $this->executeParallelGateway($instance, $definition, $token, $node, $startTime);
                } elseif ($node instanceof TimerCatchEvent) {
                    // In current synchronous engine cycle, advance timer event
                    $this->advanceToken($instance, $definition, $token, $node);
                    $this->logActivity($instance, $node, 'TIMER_PASSED', ['duration' => $node->getDurationSeconds()], [], ActivityExecution::STATUS_SUCCESS, $startTime);
                } else {
                    $this->advanceToken($instance, $definition, $token, $node);
                }
            } catch (Throwable $e) {
                $this->handleExecutionFailure($instance, $definition, $node, $e, $startTime);
                throw new NodeExecutionException($node->getId(), $instance->getId(), $e->getMessage(), (int) $e->getCode(), $e);
            }

            $this->store->saveInstance($instance);
        }

        if (count($instance->getActiveTokens()) === 0 && count($instance->getWaitingTokens()) === 0 && $instance->getStatus() === WorkflowInstanceInterface::STATUS_RUNNING) {
            $instance->setStatus(WorkflowInstanceInterface::STATUS_COMPLETED);
        }

        $this->store->saveInstance($instance);
        return $instance;
    }

    /**
     * Advance a token along the outgoing sequence flows of a node.
     */
    protected function advanceToken(ProcessInstance $instance, WorkflowDefinition $definition, Token $token, AbstractNode $node): void
    {
        $flows = $definition->getOutgoingFlowsForNode($node->getId());
        if (empty($flows)) {
            $token->setState(Token::STATE_COMPLETED);
            $instance->removeToken($token->getId());
            return;
        }

        // Single outgoing flow: advance existing token
        if (count($flows) === 1) {
            $token->setNodeId($flows[0]->getTargetRef());
            return;
        }

        // Multiple outgoing flows: first flow uses existing token, additional create new tokens
        $firstFlow = array_shift($flows);
        $token->setNodeId($firstFlow->getTargetRef());

        foreach ($flows as $flow) {
            $newToken = new Token(
                uniqid('tok_', true),
                $flow->getTargetRef(),
                Token::STATE_ACTIVE,
                $token->getId()
            );
            $instance->addToken($newToken);
        }
    }

    /**
     * Execute a Service Task.
     */
    protected function executeServiceTask(
        ProcessInstance $instance,
        WorkflowDefinition $definition,
        Token $token,
        ServiceTask $node,
        float $startTime
    ): void {
        $context = new ExecutionContext($instance, $node->getId(), $instance->getVariables());
        $inputSnapshot = $context->all();

        // Register compensation handler if specified
        if ($node->getCompensationHandler() !== null) {
            $instance->addCompensationStep(new CompensationStep(
                $node->getId(),
                $node->getCompensationHandler(),
                $inputSnapshot
            ));
        }

        $handler = $node->getHandler();
        $output = [];

        if (is_callable($handler)) {
            $output = (array) $handler($context);
        } elseif (is_string($handler) && class_exists($handler)) {
            $handlerInstance = null;
            if (class_exists(Application::class) && Application::getInstance()) {
                $handlerInstance = Application::getInstance()->make($handler);
            } else {
                $handlerInstance = new $handler();
            }

            if ($handlerInstance instanceof TaskHandlerInterface) {
                $output = (array) $handlerInstance->execute($context);
            } elseif (is_callable($handlerInstance)) {
                $output = (array) $handlerInstance($context);
            }
        }

        if (!empty($output)) {
            $instance->mergeVariables($output);
        }

        $this->logActivity($instance, $node, 'SERVICE_EXECUTE', $inputSnapshot, $output, ActivityExecution::STATUS_SUCCESS, $startTime);
        $this->advanceToken($instance, $definition, $token, $node);
    }

    /**
     * Execute a Script Task.
     */
    protected function executeScriptTask(
        ProcessInstance $instance,
        WorkflowDefinition $definition,
        Token $token,
        ScriptTask $node,
        float $startTime
    ): void {
        $context = new ExecutionContext($instance, $node->getId(), $instance->getVariables());
        $inputSnapshot = $context->all();
        $script = $node->getScript();
        $output = [];

        if (is_callable($script)) {
            $output = (array) $script($context);
        }

        if (!empty($output)) {
            $instance->mergeVariables($output);
        }

        $this->logActivity($instance, $node, 'SCRIPT_EXECUTE', $inputSnapshot, $output, ActivityExecution::STATUS_SUCCESS, $startTime);
        $this->advanceToken($instance, $definition, $token, $node);
    }

    /**
     * Execute Exclusive Gateway (XOR).
     */
    protected function executeExclusiveGateway(
        ProcessInstance $instance,
        WorkflowDefinition $definition,
        Token $token,
        ExclusiveGateway $node,
        float $startTime
    ): void {
        $flows = $definition->getOutgoingFlowsForNode($node->getId());
        $selectedFlow = null;
        $defaultFlow = null;

        foreach ($flows as $flow) {
            if ($flow->isDefault() || $flow->getId() === $node->getDefaultFlowId()) {
                $defaultFlow = $flow;
                continue;
            }

            if ($flow->evaluateCondition($instance->getVariables())) {
                $selectedFlow = $flow;
                break;
            }
        }

        if (!$selectedFlow) {
            $selectedFlow = $defaultFlow;
        }

        if (!$selectedFlow) {
            if (count($flows) > 0) {
                $selectedFlow = $flows[0];
            } else {
                throw new WorkflowException("No outgoing flow evaluated to true for ExclusiveGateway [{$node->getId()}].");
            }
        }

        $token->setNodeId($selectedFlow->getTargetRef());
        $this->logActivity($instance, $node, 'XOR_EVALUATE', ['selectedFlow' => $selectedFlow->getId(), 'target' => $selectedFlow->getTargetRef()], [], ActivityExecution::STATUS_SUCCESS, $startTime);
    }

    /**
     * Execute Parallel Gateway (AND - Fork / Join).
     */
    protected function executeParallelGateway(
        ProcessInstance $instance,
        WorkflowDefinition $definition,
        Token $token,
        ParallelGateway $node,
        float $startTime
    ): void {
        $incomingFlows = $definition->getIncomingFlowsForNode($node->getId());
        $outgoingFlows = $definition->getOutgoingFlowsForNode($node->getId());

        // Join logic: if incoming flows > 1
        if (count($incomingFlows) > 1) {
            // Check if other tokens have arrived at this gateway
            $arrivedTokens = [];
            foreach ($instance->getTokens() as $t) {
                if ($t->getNodeId() === $node->getId()) {
                    $arrivedTokens[] = $t;
                }
            }

            if (count($arrivedTokens) < count($incomingFlows)) {
                // Not all branches arrived yet, put current token in waiting state
                $token->setState(Token::STATE_WAITING);
                $this->logActivity($instance, $node, 'AND_JOIN_WAIT', ['arrived' => count($arrivedTokens), 'required' => count($incomingFlows)], [], ActivityExecution::STATUS_SUCCESS, $startTime);
                return;
            }

            // All branches arrived! Keep the first token active and remove all other joined tokens
            $primaryToken = $arrivedTokens[0];
            $primaryToken->setState(Token::STATE_ACTIVE);

            for ($i = 1; $i < count($arrivedTokens); $i++) {
                $instance->removeToken($arrivedTokens[$i]->getId());
            }

            $this->advanceToken($instance, $definition, $primaryToken, $node);
            $this->logActivity($instance, $node, 'AND_JOIN', ['arrived' => count($arrivedTokens)], [], ActivityExecution::STATUS_SUCCESS, $startTime);
            return;
        }

        // Fork logic: advance to all outgoing flows
        $this->advanceToken($instance, $definition, $token, $node);
        $this->logActivity($instance, $node, 'AND_FORK', ['outgoingCount' => count($outgoingFlows)], [], ActivityExecution::STATUS_SUCCESS, $startTime);
    }

    /**
     * Handle execution failure and trigger Saga rollback compensation if needed.
     */
    protected function handleExecutionFailure(
        ProcessInstance $instance,
        WorkflowDefinition $definition,
        AbstractNode $node,
        Throwable $exception,
        float $startTime
    ): void {
        $this->logActivity($instance, $node, 'ERROR', [], [], ActivityExecution::STATUS_ERROR, $startTime, $exception->getMessage());

        // Check if there are compensation steps
        if (count($instance->getCompensationStack()) > 0) {
            $instance->setStatus(WorkflowInstanceInterface::STATUS_COMPENSATING);
            $result = $this->sagaCoordinator->compensate($instance, $exception);

            if ($result->isSuccess()) {
                $instance->setStatus(WorkflowInstanceInterface::STATUS_COMPENSATED);
            } else {
                $instance->setStatus(WorkflowInstanceInterface::STATUS_FAILED);
            }
        } else {
            $instance->setStatus(WorkflowInstanceInterface::STATUS_FAILED);
        }

        $this->store->saveInstance($instance);
    }

    /**
     * Log activity execution record.
     */
    protected function logActivity(
        ProcessInstance $instance,
        AbstractNode $node,
        string $action,
        array $input,
        array $output,
        string $status,
        float $startTime,
        ?string $errorMessage = null
    ): void {
        $durationMs = (microtime(true) - $startTime) * 1000;
        $act = new ActivityExecution(
            uniqid('act_', true),
            $instance->getId(),
            $node->getId(),
            $node->getType(),
            $action,
            $input,
            $output,
            $status,
            $durationMs,
            $errorMessage
        );

        $instance->addHistory($act);
        $this->store->recordActivity($act);
    }
}
