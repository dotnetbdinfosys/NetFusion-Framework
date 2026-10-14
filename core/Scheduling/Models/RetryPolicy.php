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

namespace NetFusion\Core\Scheduling\Models;

class RetryPolicy
{
    /**
     * @param int $maxAttempts Maximum number of retry attempts (1 = no retry)
     * @param int $delaySeconds Base delay before first retry in seconds
     * @param bool $exponentialBackoff Whether delay should double on each attempt
     * @param float $multiplier Multiplier for exponential backoff (default 2.0)
     */
    public function __construct(
        public readonly int $maxAttempts = 3,
        public readonly int $delaySeconds = 5,
        public readonly bool $exponentialBackoff = true,
        public readonly float $multiplier = 2.0
    ) {
    }

    public static function none(): self
    {
        return new self(maxAttempts: 1, delaySeconds: 0);
    }

    public static function standard(int $attempts = 3, int $delay = 5): self
    {
        return new self(maxAttempts: $attempts, delaySeconds: $delay, exponentialBackoff: true);
    }

    public function getDelayForAttempt(int $attempt): int
    {
        if ($attempt <= 0) {
            return 0;
        }

        if (!$this->exponentialBackoff) {
            return $this->delaySeconds;
        }

        $multiplier = pow($this->multiplier, $attempt - 1);
        return (int)round($this->delaySeconds * $multiplier);
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function getDelaySeconds(): int
    {
        return $this->delaySeconds;
    }

    public function isExponentialBackoff(): bool
    {
        return $this->exponentialBackoff;
    }

    public function getMultiplier(): float
    {
        return $this->multiplier;
    }
}
