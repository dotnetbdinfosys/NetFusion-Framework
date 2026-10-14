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

namespace NetFusion\Core\Saga;

use NetFusion\Core\Saga\Contracts\SagaInterface;
use NetFusion\Core\Saga\Contracts\SagaStepInterface;
use NetFusion\Core\Saga\Contracts\SagaStoreInterface;
use Throwable;

class SagaCoordinator
{
    /** @var array<callable> */
    protected array $stepListeners = [];

    public function __construct(
        protected ?SagaStoreInterface $store = null
    ) {
    }

    public function setStore(?SagaStoreInterface $store): self
    {
        $this->store = $store;
        return $this;
    }

    public function getStore(): ?SagaStoreInterface
    {
        return $this->store;
    }

    /**
     * Add a lifecycle step listener callback: function(string $event, SagaInstance $instance, SagaStepInterface $step, mixed $data)
     */
    public function onStep(callable $callback): self
    {
        $this->stepListeners[] = $callback;
        return $this;
    }

    /**
     * Execute a Saga instance through the forward and compensating steps.
     */
    public function run(SagaInterface $saga, SagaInstance $instance): SagaInstance
    {
        $steps = $saga->getSteps();
        $instance->state = SagaState::RUNNING;
        $this->persist($instance);

        try {
            $saga->onStart($instance->context);
        } catch (Throwable $e) {
            $instance->state = SagaState::FAILED;
            $instance->error = "Saga onStart failed: " . $e->getMessage();
            $instance->completedAt = microtime(true);
            $this->persist($instance);
            return $instance;
        }

        $completedSteps = [];
        $failedStep = null;
        $failureException = null;

        for ($i = 0; $i < count($steps); $i++) {
            $step = $steps[$i];
            $instance->currentStepIndex = $i;
            $this->notify('step_started', $instance, $step, null);

            $stepStartTime = microtime(true);
            $attempts = 0;
            $maxAttempts = 1 + $step->getRetries();
            $lastException = null;
            $stepSuccess = false;
            $stepResultValue = null;

            while ($attempts < $maxAttempts) {
                $attempts++;
                try {
                    $stepResultValue = $step->execute($instance->context);
                    $stepSuccess = true;
                    break;
                } catch (Throwable $e) {
                    $lastException = $e;
                    if ($attempts < $maxAttempts && $step instanceof SagaStep && $step->getRetryDelayMs() > 0) {
                        usleep((int)($step->getRetryDelayMs() * 1000));
                    }
                }
            }

            $stepDurationMs = round((microtime(true) - $stepStartTime) * 1000, 2);

            if ($stepSuccess) {
                $stepResObj = SagaStepResult::success($step->getName(), $stepResultValue, $stepDurationMs);
                $instance->recordStepResult($stepResObj);
                $completedSteps[] = $step;
                $this->persist($instance);
                $this->notify('step_completed', $instance, $step, $stepResultValue);
            } else {
                $errMsg = $lastException !== null ? $lastException->getMessage() : 'Unknown step execution failure.';
                $stepResObj = SagaStepResult::failure($step->getName(), $errMsg, $stepDurationMs);
                $instance->recordStepResult($stepResObj);
                $instance->error = "Step [{$step->getName()}] failed: " . $errMsg;
                $this->persist($instance);
                $this->notify('step_failed', $instance, $step, $errMsg);

                $failedStep = $step;
                $failureException = $lastException ?? new SagaException($errMsg);
                break;
            }
        }

        // Check if any step failed
        if ($failedStep !== null) {
            $this->executeCompensation($saga, $instance, $completedSteps, $failureException);
            return $instance;
        }

        // All forward steps completed successfully!
        $instance->state = SagaState::COMPLETED;
        $instance->completedAt = microtime(true);
        $this->persist($instance);

        try {
            $saga->onComplete($instance->context);
        } catch (Throwable) {
            // Log or ignore onComplete hook error
        }

        return $instance;
    }

    /**
     * Execute backward compensating rollback across all previously completed steps in reverse order.
     *
     * @param SagaInterface $saga
     * @param SagaInstance $instance
     * @param SagaStepInterface[] $completedSteps
     * @param Throwable $error
     */
    public function executeCompensation(
        SagaInterface $saga,
        SagaInstance $instance,
        array $completedSteps,
        Throwable $error
    ): void {
        $instance->state = SagaState::COMPENSATING;
        $this->persist($instance);

        try {
            $saga->onCompensating($instance->context, $error);
        } catch (Throwable) {
            // Ignore hook error
        }

        // Reverse completed steps for compensation rollback
        $stepsToCompensate = array_reverse($completedSteps);
        $compensationFailed = false;

        foreach ($stepsToCompensate as $step) {
            $this->notify('compensation_started', $instance, $step, null);
            try {
                $step->compensate($instance->context);

                $res = $instance->getStepResult($step->getName());
                if ($res !== null) {
                    $res->markCompensated();
                }
                $this->persist($instance);
                $this->notify('compensation_completed', $instance, $step, null);
            } catch (Throwable $compError) {
                $compensationFailed = true;
                $instance->error .= " | Compensation for [{$step->getName()}] failed: " . $compError->getMessage();
                $this->persist($instance);
                $this->notify('compensation_failed', $instance, $step, $compError->getMessage());
            }
        }

        $instance->state = $compensationFailed ? SagaState::FAILED : SagaState::COMPENSATED;
        $instance->completedAt = microtime(true);
        $this->persist($instance);

        try {
            $saga->onCompensated($instance->context);
        } catch (Throwable) {
            // Ignore hook error
        }
    }

    protected function persist(SagaInstance $instance): void
    {
        if ($this->store !== null) {
            $this->store->save($instance);
        }
    }

    protected function notify(string $event, SagaInstance $instance, ?SagaStepInterface $step, mixed $data): void
    {
        foreach ($this->stepListeners as $listener) {
            $listener($event, $instance, $step, $data);
        }
    }
}
