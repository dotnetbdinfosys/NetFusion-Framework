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

class NodeExecutionException extends WorkflowException
{
    protected string $nodeId;
    protected string $instanceId;

    public function __construct(string $nodeId, string $instanceId, string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        $this->nodeId = $nodeId;
        $this->instanceId = $instanceId;
        parent::__construct("Error executing node [{$nodeId}] in instance [{$instanceId}]: " . ($message ?: $previous?->getMessage() ?? 'Unknown error'), $code, $previous);
    }

    public function getNodeId(): string
    {
        return $this->nodeId;
    }

    public function getInstanceId(): string
    {
        return $this->instanceId;
    }
}
