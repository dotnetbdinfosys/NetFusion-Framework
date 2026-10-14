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

namespace NetFusion\Core\Scheduling\Contracts;

use NetFusion\Core\Scheduling\Models\TaskExecution;
use NetFusion\Core\Scheduling\Models\RetryPolicy;

interface TaskInterface
{
    public function getId(): string;
    public function getName(): string;
    public function getExpression(): string;
    public function getTimezone(): string;
    public function isOverlapAllowed(): bool;
    public function getTimeout(): int;
    public function getMisfirePolicy(): string;
    public function getRetryPolicy(): ?RetryPolicy;
    public function isDue(?\DateTimeInterface $currentTime = null): bool;
    public function execute(): TaskExecution;
}
