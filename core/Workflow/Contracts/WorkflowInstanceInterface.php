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

interface WorkflowInstanceInterface
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_RUNNING = 'RUNNING';
    public const STATUS_SUSPENDED = 'SUSPENDED';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_CANCELLED = 'CANCELLED';
    public const STATUS_COMPENSATING = 'COMPENSATING';
    public const STATUS_COMPENSATED = 'COMPENSATED';

    public function getId(): string;
    public function getDefinitionId(): string;
    public function getStatus(): string;
    public function getVariables(): array;
    public function getVariable(string $key, mixed $default = null): mixed;
    public function setVariable(string $key, mixed $value): self;
    public function isCompleted(): bool;
    public function isSuspended(): bool;
    public function isFailed(): bool;
    public function toArray(): array;
}
