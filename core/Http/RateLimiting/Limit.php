<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Http/RateLimiting/Limit.php
| Module    : Http\RateLimiting
| Namespace : NetFusion\Core\Http\RateLimiting
| Purpose   : Rate limit definition specification object.
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

namespace NetFusion\Core\Http\RateLimiting;

/**
 * Class Limit
 *
 * Defines a rate limit configuration including max attempts, decay seconds, and grouping key.
 *
 * @package NetFusion\Core\Http\RateLimiting
 */
class Limit
{
    /**
     * @param int         $maxAttempts  Maximum requests allowed.
     * @param int         $decaySeconds Time window in seconds.
     * @param string|null $key          Optional rate limiting partition key.
     */
    public function __construct(
        public readonly int $maxAttempts,
        public readonly int $decaySeconds = 60,
        public ?string $key = null
    ) {
    }

    /**
     * Create a limit per minute.
     */
    public static function perMinute(int $maxAttempts): self
    {
        return new self($maxAttempts, 60);
    }

    /**
     * Create a limit per specified minutes.
     */
    public static function perMinutes(int $decayMinutes, int $maxAttempts): self
    {
        return new self($maxAttempts, $decayMinutes * 60);
    }

    /**
     * Create a limit per hour.
     */
    public static function perHour(int $maxAttempts): self
    {
        return new self($maxAttempts, 3600);
    }

    /**
     * Create a limit per day.
     */
    public static function perDay(int $maxAttempts): self
    {
        return new self($maxAttempts, 86400);
    }

    /**
     * Create an unlimited rate limit.
     */
    public static function none(): self
    {
        return new self(PHP_INT_MAX, 60);
    }

    /**
     * Set the key to partition rate limits by (e.g. IP, user ID).
     */
    public function by(?string $key): self
    {
        $this->key = $key;
        return $this;
    }
}
