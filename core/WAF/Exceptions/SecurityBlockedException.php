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

namespace NetFusion\Core\WAF\Exceptions;

class SecurityBlockedException extends WAFException
{
    /**
     * @param string $reason Reason for blocking
     * @param string $ruleId Triggered rule ID
     * @param float $anomalyScore Total anomaly score
     * @param string|null $clientIp Offending client IP
     */
    public function __construct(
        string $reason,
        protected string $ruleId = 'WAF_GENERIC_BLOCK',
        protected float $anomalyScore = 5.0,
        protected ?string $clientIp = null
    ) {
        parent::__construct("WAF Security Block [{$ruleId}]: {$reason}");
    }

    public function getRuleId(): string
    {
        return $this->ruleId;
    }

    public function getAnomalyScore(): float
    {
        return $this->anomalyScore;
    }

    public function getClientIp(): ?string
    {
        return $this->clientIp;
    }
}
