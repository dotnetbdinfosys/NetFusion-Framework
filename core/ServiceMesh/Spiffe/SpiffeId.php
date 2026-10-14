<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
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

declare(strict_types=1);

namespace NetFusion\Core\ServiceMesh\Spiffe;

use InvalidArgumentException;

/**
 * Class SpiffeId
 *
 * Implements the SPIFFE ID Standard (RFC/SPIFFE Standard §2).
 * Format: spiffe://<trust-domain>/<workload-path>
 * Example: spiffe://cluster.local/ns/prod/sa/order-service
 */
final class SpiffeId
{
    private readonly string $trustDomain;
    private readonly string $path;
    private readonly string $raw;

    public function __construct(string $uri)
    {
        $uri = trim($uri);
        if (!str_starts_with($uri, 'spiffe://')) {
            throw new InvalidArgumentException("Invalid SPIFFE ID scheme. Must begin with 'spiffe://', got: '{$uri}'");
        }

        $remainder = substr($uri, 9); // After spiffe://
        $slashPos = strpos($remainder, '/');

        if ($slashPos === false) {
            $this->trustDomain = strtolower($remainder);
            $this->path = '';
        } else {
            $this->trustDomain = strtolower(substr($remainder, 0, $slashPos));
            $this->path = substr($remainder, $slashPos);
        }

        if (empty($this->trustDomain)) {
            throw new InvalidArgumentException("Invalid SPIFFE ID: Trust domain cannot be empty.");
        }

        $this->raw = 'spiffe://' . $this->trustDomain . $this->path;
    }

    public static function of(string $trustDomain, string $path): self
    {
        $path = '/' . ltrim($path, '/');
        return new self("spiffe://{$trustDomain}{$path}");
    }

    public static function isValid(string $uri): bool
    {
        try {
            new self($uri);
            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    public function getTrustDomain(): string
    {
        return $this->trustDomain;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getWorkloadName(): string
    {
        $parts = explode('/', trim($this->path, '/'));
        return end($parts) ?: '';
    }

    public function getNamespace(): ?string
    {
        $parts = explode('/', trim($this->path, '/'));
        $nsIdx = array_search('ns', $parts, true);
        if ($nsIdx !== false && isset($parts[$nsIdx + 1])) {
            return $parts[$nsIdx + 1];
        }
        return null;
    }

    public function matches(string|SpiffeId $pattern): bool
    {
        $patternStr = (string) $pattern;
        if ($this->raw === $patternStr) {
            return true;
        }

        if (str_ends_with($patternStr, '/*')) {
            $prefix = substr($patternStr, 0, -2);
            return str_starts_with($this->raw, $prefix);
        }

        if (str_ends_with($patternStr, '*')) {
            $prefix = substr($patternStr, 0, -1);
            return str_starts_with($this->raw, $prefix);
        }

        return false;
    }

    public function equals(SpiffeId $other): bool
    {
        return $this->raw === $other->raw;
    }

    public function __toString(): string
    {
        return $this->raw;
    }
}
