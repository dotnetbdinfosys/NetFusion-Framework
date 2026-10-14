<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | Author    : Md. Atiquzzaman Ashish
 | Company   : DotNetBD
 | Website   : www.dotnetbd.com
 | Copyright : © DotNetBD. All rights reserved.
 | License   : MIT
 | Version   : 1.0.0
 |
 |--------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace NetFusion\Core\Accounting\Contracts;

/**
 * Interface ReportInterface
 *
 * Contract for financial statement generators.
 * Each report can produce an array representation and a
 * formatted plain-text output for CLI display.
 */
interface ReportInterface
{
    /** @return string Report title */
    public function getTitle(): string;

    /** @return string The reporting period label */
    public function getPeriod(): string;

    /** @return array<string, mixed> Structured report data */
    public function toArray(): array;

    /**
     * Return a human-readable text rendering of the report
     * (column-aligned for terminal output).
     */
    public function render(): string;
}
