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
 * Interface AccountInterface
 *
 * Contract for a Chart-of-Accounts entry.
 * Every account belongs to one of the five fundamental account types:
 * asset, liability, equity, income, or expense.
 */
interface AccountInterface
{
    /** @return string Unique account code (e.g., "1001") */
    public function getCode(): string;

    /** @return string Human-readable name */
    public function getName(): string;

    /**
     * @return string One of: asset | liability | equity | income | expense
     */
    public function getType(): string;

    /**
     * Normal balance side for this account type.
     * @return string "debit" or "credit"
     */
    public function getNormalBalance(): string;

    /** @return string|null Parent account code for sub-accounts */
    public function getParentCode(): ?string;

    public function isActive(): bool;
}
