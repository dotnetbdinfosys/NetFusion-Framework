<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Accounting/Models/JournalItem.php
| Module    : Modules\Accounting
| Namespace : NetFusion\Modules\Accounting\Models
| Purpose   : Journal Item model representing individual debit/credit entries in a voucher.
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
 * Class JournalItem
 *
 * @property int $id
 * @property int $journal_entry_id
 * @property int $account_id
 * @property float $debit
 * @property float $credit
 * @property string|null $description
 *
 * @package NetFusion\Modules\Accounting\Models
 */
class JournalItem extends Model
{
    protected string $table = 'acc_journal_items';

    protected array $fillable = [
        'journal_entry_id',
        'account_id',
        'debit',
        'credit',
        'description',
        'created_at',
        'updated_at',
    ];

    /**
     * Get the account for this line item.
     */
    public function account(): ?Account
    {
        return Account::find($this->account_id);
    }

    /**
     * Get the parent journal entry.
     */
    public function journalEntry(): ?JournalEntry
    {
        return JournalEntry::find($this->journal_entry_id);
    }
}
