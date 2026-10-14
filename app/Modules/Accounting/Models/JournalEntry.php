<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Accounting/Models/JournalEntry.php
| Module    : Modules\Accounting
| Namespace : NetFusion\Modules\Accounting\Models
| Purpose   : General Journal voucher model encapsulating double-entry transactions.
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
 * Class JournalEntry
 *
 * @property int $id
 * @property string $entry_number
 * @property string $entry_date
 * @property string|null $reference
 * @property string $description
 * @property string $status
 * @property float $total_amount
 * @property string|null $posted_at
 * @property string $created_at
 *
 * @package NetFusion\Modules\Accounting\Models
 */
class JournalEntry extends Model
{
    protected string $table = 'acc_journal_entries';

    protected array $fillable = [
        'entry_number',
        'entry_date',
        'reference',
        'description',
        'status',
        'total_amount',
        'posted_at',
        'created_at',
        'updated_at',
    ];

    public const STATUS_DRAFT  = 'draft';
    public const STATUS_POSTED = 'posted';

    /**
     * Get all journal items belonging to this entry.
     *
     * @return array<int, JournalItem>
     */
    public function items(): array
    {
        return JournalItem::where('journal_entry_id', '=', $this->id)->get();
    }

    /**
     * Determine if this entry is balanced (sum of debits equals sum of credits).
     */
    public function isBalanced(): bool
    {
        $items = $this->items();
        if (empty($items)) {
            return false;
        }

        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($items as $item) {
            $debit = is_array($item) ? (float) ($item['debit'] ?? 0.0) : (float) $item->debit;
            $credit = is_array($item) ? (float) ($item['credit'] ?? 0.0) : (float) $item->credit;
            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        // Float comparison with epsilon tolerance (0.001)
        return abs($totalDebit - $totalCredit) < 0.001 && $totalDebit > 0.0;
    }
}
