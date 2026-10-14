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

interface CronParserInterface
{
    public function isDue(string $expression, ?\DateTimeInterface $currentTime = null, string $timezone = 'UTC'): bool;
    public function getNextRunDate(string $expression, ?\DateTimeInterface $currentTime = null, string $timezone = 'UTC'): \DateTimeImmutable;
    public function getPreviousRunDate(string $expression, ?\DateTimeInterface $currentTime = null, string $timezone = 'UTC'): \DateTimeImmutable;
    public function getNextRunDates(string $expression, int $count = 5, ?\DateTimeInterface $currentTime = null, string $timezone = 'UTC'): array;
}
