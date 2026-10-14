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

namespace NetFusion\Core\Payment\Contracts;

interface ReconciliationEngineInterface
{
    /**
     * Ingest bank/processor settlement statement rows.
     *
     * @param string $processor
     * @param array<int, array{transaction_id: string, amount: float, fee: float, status: string, date: string}> $statementRows
     * @return array{matched: int, unmatched: int, fee_variance: float, discrepancies: array}
     */
    public function reconcile(string $processor, array $statementRows): array;
}
