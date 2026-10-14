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

namespace NetFusion\Core\IMDG\NearCache;

class NearCacheConfig
{
    protected int $maxSize;
    protected int $ttlSeconds;
    protected int $ttiSeconds;
    protected string $evictionPolicy;
    protected bool $invalidateOnChange;

    public function __construct(
        int $maxSize = 5000,
        int $ttlSeconds = 300,
        int $ttiSeconds = 60,
        string $evictionPolicy = 'lru',
        bool $invalidateOnChange = true
    ) {
        $this->maxSize = $maxSize;
        $this->ttlSeconds = $ttlSeconds;
        $this->ttiSeconds = $ttiSeconds;
        $this->evictionPolicy = strtolower($evictionPolicy);
        $this->invalidateOnChange = $invalidateOnChange;
    }

    public function getMaxSize(): int
    {
        return $this->maxSize;
    }

    public function getTtlSeconds(): int
    {
        return $this->ttlSeconds;
    }

    public function getTtiSeconds(): int
    {
        return $this->ttiSeconds;
    }

    public function getEvictionPolicy(): string
    {
        return $this->evictionPolicy;
    }

    public function isInvalidateOnChange(): bool
    {
        return $this->invalidateOnChange;
    }
}
