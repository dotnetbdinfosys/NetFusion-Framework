<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Accounting/Controllers/Api/ReportApiController.php
| Module    : Modules\Accounting
| Namespace : NetFusion\Modules\Accounting\Controllers\Api
| Purpose   : REST API Controller delivering financial reports (Trial Balance, P&L, Balance Sheet).
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

namespace NetFusion\Modules\Accounting\Controllers\Api;

use NetFusion\Core\Http\ApiResponse;
use NetFusion\Core\Http\JsonResponse;
use NetFusion\Core\Http\Request;
use NetFusion\Core\OpenApi\Attributes\ApiEndpoint;
use NetFusion\Modules\Accounting\Services\LedgerService;

/**
 * Class ReportApiController
 *
 * @package NetFusion\Modules\Accounting\Controllers\Api
 */
class ReportApiController
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    #[ApiEndpoint(
        summary: 'Generate Trial Balance',
        description: 'Calculates debit and credit balances for all active ledger accounts.',
        tags: ['Accounting'],
        security: ['bearerAuth']
    )]
    public function trialBalance(Request $request): JsonResponse
    {
        $report = $this->ledgerService->getTrialBalance();
        return ApiResponse::success($report);
    }

    #[ApiEndpoint(
        summary: 'Generate Income Statement (Profit & Loss)',
        description: 'Calculates revenues, expenses, and current net income.',
        tags: ['Accounting'],
        security: ['bearerAuth']
    )]
    public function incomeStatement(Request $request): JsonResponse
    {
        $report = $this->ledgerService->getIncomeStatement();
        return ApiResponse::success($report);
    }

    #[ApiEndpoint(
        summary: 'Generate Balance Sheet',
        description: 'Generates Assets = Liabilities + Equity statement.',
        tags: ['Accounting'],
        security: ['bearerAuth']
    )]
    public function balanceSheet(Request $request): JsonResponse
    {
        $report = $this->ledgerService->getBalanceSheet();
        return ApiResponse::success($report);
    }
}
