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

interface SagaStepInterface
{
    /**
     * Get the unique step name.
     */
    public function getName(): string;

    /**
     * Execute the forward transaction action.
     *
     * @param SagaContext $context
     * @return mixed
     */
    public function execute(SagaContext $context): mixed;

    /**
     * Execute the backward compensating rollback action.
     *
     * @param SagaContext $context
     * @return mixed
     */
    public function compensate(SagaContext $context): mixed;

    /**
     * Max retry attempts for transient errors.
     */
    public function getRetries(): int;

    /**
     * Timeout in seconds for step execution.
     */
    public function getTimeout(): float;
}
