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

namespace NetFusion\Core\Payment\Models;

class SplitRule
{
    /**
     * @param string $vendorId Vendor/Merchant identifier
     * @param float $percentage Percentage cut (e.g. 80.0 for 80%)
     * @param float $fixedFee Fixed amount to vendor or fee deduction
     * @param int $delayDays Escrow hold delay in days before payout
     */
    public function __construct(
        protected string $vendorId,
        protected float $percentage = 0.0,
        protected float $fixedFee = 0.0,
        protected int $delayDays = 0
    ) {
    }

    public function getVendorId(): string
    {
        return $this->vendorId;
    }

    public function getPercentage(): float
    {
        return $this->percentage;
    }

    public function getFixedFee(): float
    {
        return $this->fixedFee;
    }

    public function getDelayDays(): int
    {
        return $this->delayDays;
    }

    /**
     * Calculate payout amount for this vendor from gross total.
     */
    public function calculateAmount(float $grossAmount): float
    {
        $share = ($grossAmount * ($this->percentage / 100.0)) + $this->fixedFee;
        return max(0.0, min($grossAmount, $share));
    }
}
