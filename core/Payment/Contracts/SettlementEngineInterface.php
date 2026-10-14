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

use NetFusion\Core\Payment\Models\PaymentResult;
use NetFusion\Core\Payment\Models\SplitRule;
use NetFusion\Core\Payment\Models\SettlementBatch;

interface SettlementEngineInterface
{
    /**
     * Compute and record split settlement allocations for a transaction.
     *
     * @param PaymentResult $result
     * @param array<SplitRule> $splitRules
     * @param float $platformFee
     * @return array<string, float> VendorId => SplitAmount
     */
    public function calculateSplits(PaymentResult $result, array $splitRules, float $platformFee = 0.0): array;

    /**
     * Create a scheduled settlement batch for pending payouts.
     */
    public function createBatch(string $currency): SettlementBatch;

    /**
     * Execute payout for a settlement batch.
     */
    public function processBatch(SettlementBatch $batch): bool;
}
