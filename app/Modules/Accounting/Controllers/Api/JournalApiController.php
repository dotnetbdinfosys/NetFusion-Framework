<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Accounting/Controllers/Api/JournalApiController.php
| Module    : Modules\Accounting
| Namespace : NetFusion\Modules\Accounting\Controllers\Api
| Purpose   : REST API Controller for creating, querying, and posting journal entries.
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

use InvalidArgumentException;
use NetFusion\Core\Http\ApiResponse;
use NetFusion\Core\Http\JsonResponse;
use NetFusion\Core\Http\Request;
use NetFusion\Core\OpenApi\Attributes\ApiEndpoint;
use NetFusion\Modules\Accounting\Models\JournalEntry;
use NetFusion\Modules\Accounting\Services\LedgerService;

/**
 * Class JournalApiController
 *
 * @package NetFusion\Modules\Accounting\Controllers\Api
 */
class JournalApiController
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    #[ApiEndpoint(
        summary: 'List general journal entries',
        description: 'Returns all journal vouchers and their status.',
        tags: ['Accounting'],
        security: ['bearerAuth']
    )]
    public function index(Request $request): JsonResponse
    {
        $entries = JournalEntry::orderBy('id', 'DESC')->limit(50)->get();
        return ApiResponse::success($entries);
    }

    #[ApiEndpoint(
        summary: 'Create double-entry journal voucher',
        description: 'Enforces balanced debits and credits and persists the journal entry and items.',
        tags: ['Accounting'],
        security: ['bearerAuth']
    )]
    public function store(Request $request): JsonResponse
    {
        $body = !empty($request->json()) ? $request->json() : $request->all();

        $entryData = [
            'entry_number' => $body['entry_number'] ?? null,
            'entry_date'   => $body['entry_date'] ?? date('Y-m-d'),
            'reference'    => $body['reference'] ?? null,
            'description'  => $body['description'] ?? 'API Journal Entry',
            'status'       => $body['status'] ?? JournalEntry::STATUS_DRAFT,
        ];

        $items = $body['items'] ?? [];

        if (empty($items) || !is_array($items)) {
            return ApiResponse::error('Journal items array is required.', 422);
        }

        try {
            $entry = $this->ledgerService->createEntry($entryData, $items);
            return ApiResponse::created($entry->toArray(), 'Journal entry recorded successfully.');
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    #[ApiEndpoint(
        summary: 'Post journal entry to ledger',
        description: 'Validates double-entry balance and locks entry status as posted.',
        tags: ['Accounting'],
        security: ['bearerAuth']
    )]
    public function post(Request $request): JsonResponse
    {
        $id = (int) ($request->routeParam('id') ?? $request->input('id'));
        $entry = JournalEntry::find($id);

        if (!$entry) {
            return ApiResponse::error('Journal entry not found.', 404);
        }

        try {
            $this->ledgerService->postEntry($entry);
            return ApiResponse::success($entry->toArray(), 'Journal entry posted to general ledger.');
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }
}
