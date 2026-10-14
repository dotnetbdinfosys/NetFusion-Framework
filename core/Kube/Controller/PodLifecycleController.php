<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\Kube\Controller;

use NetFusion\Core\Kube\Enums\ContainerState;
use NetFusion\Core\Kube\Enums\PodPhase;
use NetFusion\Core\Kube\Enums\RestartPolicy;
use NetFusion\Core\Kube\Models\Pod;

/**
 * Manages Pod lifecycle transitions, container startup, health probes, and restarts.
 */
class PodLifecycleController
{
    public function __construct(
        protected ?ProbeExecutor $probeExecutor = null
    ) {
        $this->probeExecutor ??= new ProbeExecutor();
    }

    public function getProbeExecutor(): ProbeExecutor
    {
        return $this->probeExecutor;
    }

    /**
     * Start a scheduled pod (transition from Pending to Running).
     */
    public function startPod(Pod $pod): void
    {
        if ($pod->isTerminal()) {
            return;
        }

        $now = microtime(true);
        foreach ($pod->getContainers() as $container) {
            $status = $pod->getContainerStatus($container->getName());
            if ($status !== null) {
                $status->setState(ContainerState::RUNNING);
                $status->setStartedAt($now);
                // If container has no readiness probe, mark it immediately ready
                if ($container->getReadinessProbe() === null) {
                    $status->setReady(true);
                }
            }
        }

        $pod->setPhase(PodPhase::RUNNING);
    }

    /**
     * Run a health check reconciliation cycle on an active Pod.
     */
    public function reconcilePod(Pod $pod): void
    {
        if ($pod->getPhase() !== PodPhase::RUNNING) {
            return;
        }

        foreach ($pod->getContainers() as $container) {
            $status = $pod->getContainerStatus($container->getName());
            if ($status === null || $status->getState() !== ContainerState::RUNNING) {
                continue;
            }

            // 1. Startup Probe check
            $startup = $container->getStartupProbe();
            if ($startup !== null) {
                $passed = $this->probeExecutor->execute($pod, $container, $startup);
                if (!$passed) {
                    $status->incrementProbeFailures('startup');
                    if ($status->getProbeFailures('startup') >= $startup->getFailureThreshold()) {
                        $this->handleContainerFailure($pod, $container->getName(), 'StartupProbeFailed');
                    }
                    continue; // Skip liveness & readiness while startup probe has not succeeded
                }
            }

            // 2. Liveness Probe check
            $liveness = $container->getLivenessProbe();
            if ($liveness !== null) {
                $passed = $this->probeExecutor->execute($pod, $container, $liveness);
                if (!$passed) {
                    $status->incrementProbeFailures('liveness');
                    if ($status->getProbeFailures('liveness') >= $liveness->getFailureThreshold()) {
                        $this->handleContainerFailure($pod, $container->getName(), 'LivenessProbeFailed');
                        continue;
                    }
                } else {
                    $status->incrementProbeSuccesses('liveness');
                }
            }

            // 3. Readiness Probe check
            $readiness = $container->getReadinessProbe();
            if ($readiness !== null) {
                $passed = $this->probeExecutor->execute($pod, $container, $readiness);
                if ($passed) {
                    $status->incrementProbeSuccesses('readiness');
                    if ($status->getProbeSuccesses('readiness') >= $readiness->getSuccessThreshold()) {
                        $status->setReady(true);
                    }
                } else {
                    $status->incrementProbeFailures('readiness');
                    if ($status->getProbeFailures('readiness') >= $readiness->getFailureThreshold()) {
                        $status->setReady(false);
                    }
                }
            }

        }
    }

    /**
     * Handle a failed container according to Pod restartPolicy.
     */
    public function handleContainerFailure(Pod $pod, string $containerName, string $reason): void
    {
        $status = $pod->getContainerStatus($containerName);
        if ($status === null) {
            return;
        }

        $policy = $pod->getRestartPolicy();

        if ($policy === RestartPolicy::ALWAYS || $policy === RestartPolicy::ON_FAILURE) {
            // Restart container
            $status->incrementRestartCount();
            $status->resetProbeCounters();
            $status->setReady(false);
            $status->setState(ContainerState::RUNNING);
            $status->setReason($reason);
            $status->setStartedAt(microtime(true));
        } else {
            // Never restart
            $status->setState(ContainerState::TERMINATED);
            $status->setReady(false);
            $status->setExitCode(1);
            $status->setReason($reason);
            $pod->setPhase(PodPhase::FAILED);
        }
    }

    /**
     * Gracefully terminate a Pod with exit codes.
     *
     * @param int $exitCode 0 for success, >0 for failure
     */
    public function terminatePod(Pod $pod, int $exitCode = 0, string $reason = 'Completed'): void
    {
        foreach ($pod->getContainers() as $container) {
            $status = $pod->getContainerStatus($container->getName());
            if ($status !== null) {
                $status->setState(ContainerState::TERMINATED);
                $status->setReady(false);
                $status->setExitCode($exitCode);
                $status->setReason($reason);
            }
        }

        $pod->setPhase($exitCode === 0 ? PodPhase::SUCCEEDED : PodPhase::FAILED);
    }
}
