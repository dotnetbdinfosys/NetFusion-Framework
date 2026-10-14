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

namespace NetFusion\Core\Gateway\Exceptions;

class RateLimitExceededException extends GatewayException
{
    public function __construct(
        string $message = 'Rate limit exceeded',
        private readonly string $key = '',
        private readonly int $resetAt = 0,
        int $code = 429,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getResetAt(): int
    {
        return $this->resetAt;
    }

    public function getRetryAfterSeconds(): int
    {
        $remaining = $this->resetAt - time();
        return max(0, $remaining);
    }
}
