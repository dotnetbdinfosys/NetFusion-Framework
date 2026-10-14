<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Accounting/Models/Account.php
| Module    : Modules\Accounting
| Namespace : NetFusion\Modules\Accounting\Models
| Purpose   : Chart of Accounts model representing General Ledger financial accounts.
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

namespace NetFusion\Modules\Accounting\Models;

use NetFusion\Core\Database\Model;

/**
 * Class Account
 *
 * Chart of Accounts entity (Asset, Liability, Equity, Revenue, Expense).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $type
 * @property string $normal_balance
 * @property int $is_active
 * @property string $created_at
 *
 * @package NetFusion\Modules\Accounting\Models
 */
class Account extends Model
{
    protected string $table = 'acc_chart_of_accounts';

    protected array $fillable = [
        'code',
        'name',
        'type',
        'normal_balance',
        'is_active',
        'created_at',
        'updated_at',
    ];

    /**
     * Account Type Constants
     */
    public const TYPE_ASSET     = 'asset';
    public const TYPE_LIABILITY = 'liability';
    public const TYPE_EQUITY    = 'equity';
    public const TYPE_REVENUE   = 'revenue';
    public const TYPE_EXPENSE   = 'expense';

    /**
     * Normal Balance Constants
     */
    public const BALANCE_DEBIT  = 'debit';
    public const BALANCE_CREDIT = 'credit';

    /**
     * Retrieve all journal items associated with this account.
     */
    public function journalItems(): array
    {
        return JournalItem::where('account_id', '=', $this->id)->get();
    }
}
