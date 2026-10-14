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

class SagaExecutionResult
{
    protected bool $success;
    /** @var array<string> */
    protected array $compensatedSteps;
    /** @var array<string> */
    protected array $failedSteps;
    /** @var array<string, string> */
    protected array $errors;

    public function __construct(
        bool $success = true,
        array $compensatedSteps = [],
        array $failedSteps = [],
        array $errors = []
    ) {
        $this->success = $success;
        $this->compensatedSteps = $compensatedSteps;
        $this->failedSteps = $failedSteps;
        $this->errors = $errors;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getCompensatedSteps(): array
    {
        return $this->compensatedSteps;
    }

    public function getFailedSteps(): array
    {
        return $this->failedSteps;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'compensatedSteps' => $this->compensatedSteps,
            'failedSteps' => $this->failedSteps,
            'errors' => $this->errors,
        ];
    }
}
