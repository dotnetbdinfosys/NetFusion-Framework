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

namespace NetFusion\Core\ServiceMesh\Routing;

use JsonSerializable;

class RetryPolicy implements JsonSerializable
{
    protected int $maxRetries;
    protected int $initialBackoffMs;
    protected int $maxBackoffMs;
    protected float $backoffMultiplier;
    protected bool $jitter;
    /** @var array<int> */
    protected array $retryOnStatusCodes;

    /**
     * @param int $maxRetries
     * @param int $initialBackoffMs
     * @param int $maxBackoffMs
     * @param float $backoffMultiplier
     * @param bool $jitter
     * @param array<int> $retryOnStatusCodes
     */
    public function __construct(
        int $maxRetries = 3,
        int $initialBackoffMs = 50,
        int $maxBackoffMs = 1000,
        float $backoffMultiplier = 2.0,
        bool $jitter = true,
        array $retryOnStatusCodes = [500, 502, 503, 504]
    ) {
        $this->maxRetries = max(0, $maxRetries);
        $this->initialBackoffMs = max(1, $initialBackoffMs);
        $this->maxBackoffMs = max($this->initialBackoffMs, $maxBackoffMs);
        $this->backoffMultiplier = max(1.0, $backoffMultiplier);
        $this->jitter = $jitter;
        $this->retryOnStatusCodes = $retryOnStatusCodes;
    }

    public static function of(
        int $maxRetries = 3,
        int $initialBackoffMs = 50,
        array $retryOnStatusCodes = [500, 502, 503, 504]
    ): self {
        return new self($maxRetries, $initialBackoffMs, 1000, 2.0, true, $retryOnStatusCodes);
    }

    public function shouldRetry(int $attempt, int $statusCode): bool
    {
        if ($attempt >= $this->maxRetries) {
            return false;
        }

        return in_array($statusCode, $this->retryOnStatusCodes, true);
    }

    public function getBackoffMs(int $attempt): int
    {
        $calculated = (int) ($this->initialBackoffMs * pow($this->backoffMultiplier, $attempt));
        $capped = min($this->maxBackoffMs, $calculated);

        if ($this->jitter) {
            return random_int((int) ($capped * 0.5), $capped);
        }

        return $capped;
    }

    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    public function toArray(): array
    {
        return [
            'max_retries' => $this->maxRetries,
            'initial_backoff_ms' => $this->initialBackoffMs,
            'max_backoff_ms' => $this->maxBackoffMs,
            'retry_status_codes' => $this->retryOnStatusCodes,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
