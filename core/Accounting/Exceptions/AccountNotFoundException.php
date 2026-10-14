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

namespace NetFusion\Core\Accounting\Exceptions;

/**
 * Class AccountNotFoundException
 *
 * Thrown when an account code does not exist in the Chart of Accounts.
 */
class AccountNotFoundException extends AccountingException
{
    public function __construct(string $code)
    {
        parent::__construct("Account not found: '{$code}'", 404);
    }
}
