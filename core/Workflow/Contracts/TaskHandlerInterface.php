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

namespace NetFusion\Core\Workflow\Contracts;

use NetFusion\Core\Workflow\Execution\ExecutionContext;

interface TaskHandlerInterface
{
    /**
     * Execute the task logic.
     *
     * @param ExecutionContext $context
     * @return array<string, mixed> Output variables to merge into execution context
     */
    public function execute(ExecutionContext $context): array;
}
