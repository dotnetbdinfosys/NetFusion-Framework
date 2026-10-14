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

namespace NetFusion\Core\Saga\Contracts;

use NetFusion\Core\Saga\SagaContext;

interface SagaInterface
{
    /**
     * Get the Saga definition name.
     */
    public function getName(): string;

    /**
     * Get all configured steps in execution order.
     *
     * @return SagaStepInterface[]
     */
    public function getSteps(): array;

    /**
     * Hook called before saga starts.
     */
    public function onStart(SagaContext $context): void;

    /**
     * Hook called when all steps complete successfully.
     */
    public function onComplete(SagaContext $context): void;

    /**
     * Hook called when saga fails and compensation starts.
     */
    public function onCompensating(SagaContext $context, \Throwable $error): void;

    /**
     * Hook called when all compensating actions finish.
     */
    public function onCompensated(SagaContext $context): void;
}
