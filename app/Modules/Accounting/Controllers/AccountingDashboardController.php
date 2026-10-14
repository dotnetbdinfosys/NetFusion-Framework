<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Accounting/Controllers/AccountingDashboardController.php
| Module    : Modules\Accounting
| Namespace : NetFusion\Modules\Accounting\Controllers
| Purpose   : Web dashboard controller rendering financial ledger metrics and accounts.
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

namespace NetFusion\Modules\Accounting\Controllers;

use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;
use NetFusion\Modules\Accounting\Models\Account;
use NetFusion\Modules\Accounting\Models\JournalEntry;
use NetFusion\Modules\Accounting\Services\LedgerService;

/**
 * Class AccountingDashboardController
 *
 * @package NetFusion\Modules\Accounting\Controllers
 */
class AccountingDashboardController
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * Display accounting dashboard.
     */
    public function index(Request $request): Response
    {
        $accounts = Account::where('is_active', '=', 1)->get();
        $entries  = JournalEntry::orderBy('id', 'DESC')->limit(10)->get();

        $trialBalance    = $this->ledgerService->getTrialBalance();
        $incomeStatement = $this->ledgerService->getIncomeStatement();
        $balanceSheet    = $this->ledgerService->getBalanceSheet();

        $viewHtml = (string) view('accounting::dashboard', [
            'accounts'        => $accounts,
            'recentEntries'   => $entries,
            'trialBalance'    => $trialBalance,
            'incomeStatement' => $incomeStatement,
            'balanceSheet'    => $balanceSheet,
        ]);

        return new Response($viewHtml);
    }
}
