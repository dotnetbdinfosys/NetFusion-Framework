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

namespace NetFusion\Core\Workflow\Exceptions;

use Throwable;

class CompensationFailedException extends WorkflowException
{
    protected array $failedSteps;

    public function __construct(array $failedSteps = [], string $message = 'One or more saga compensation steps failed', int $code = 0, ?Throwable $previous = null)
    {
        $this->failedSteps = $failedSteps;
        parent::__construct($message, $code, $previous);
    }

    public function getFailedSteps(): array
    {
        return $this->failedSteps;
    }
}
