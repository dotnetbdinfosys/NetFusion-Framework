<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Currency/Drivers/CacheRateDriver.php
| Module    : Currency
| Namespace : NetFusion\Core\Currency\Drivers
| Purpose   : Cache-backed decorator driver for exchange rate caching.
|             Inner driver (DB/API) এর উপর cache layer যোগ করে।
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

namespace NetFusion\Core\Currency\Drivers;

use NetFusion\Core\Cache\CacheManager;
use NetFusion\Core\Currency\Contracts\ExchangeRateDriverInterface;

/**
 * CacheRateDriver — Cache-Backed Rate Decorator
 *
 * Decorator Pattern অনুসরণ করে। Inner driver (DatabaseRateDriver বা ApiRateDriver)
 * কে wrap করে cache layer প্রদান করে, যাতে প্রতি request এ DB hit কমে।
 *
 * Cache TTL default 3600 seconds (1 ঘণ্টা)।
 */
class CacheRateDriver implements ExchangeRateDriverInterface
{
    private const CACHE_PREFIX = 'netfusion_fx_rate_';

    public function __construct(
        private readonly ExchangeRateDriverInterface $inner,
        private readonly CacheManager               $cache,
        private readonly int                        $ttl = 3600,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function getRate(string $from, string $to): string
    {
        $from = strtoupper($from);
        $to   = strtoupper($to);

        if ($from === $to) {
            return '1';
        }

        $cacheKey = self::CACHE_PREFIX . "{$from}_{$to}";

        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return (string) $cached;
        }

        $rate = $this->inner->getRate($from, $to);
        $this->cache->set($cacheKey, $rate, $this->ttl);

        return $rate;
    }

    /**
     * {@inheritDoc}
     */
    public function setRate(string $from, string $to, string $rate, ?string $source = 'manual'): void
    {
        $this->inner->setRate($from, $to, $rate, $source);

        // Cache invalidate করি নতুন rate লেখার পর
        $cacheKey = self::CACHE_PREFIX . strtoupper($from) . '_' . strtoupper($to);
        $this->cache->delete($cacheKey);

        // Bulk rates cache ও invalidate করি
        $bulkKey = self::CACHE_PREFIX . 'bulk_' . strtoupper($from);
        $this->cache->delete($bulkKey);
    }

    /**
     * {@inheritDoc}
     */
    public function getRates(string $base): array
    {
        $base     = strtoupper($base);
        $cacheKey = self::CACHE_PREFIX . "bulk_{$base}";

        $cached = $this->cache->get($cacheKey);
        if ($cached !== null && is_array($cached)) {
            return $cached;
        }

        $rates = $this->inner->getRates($base);
        $this->cache->set($cacheKey, $rates, $this->ttl);

        return $rates;
    }

    /**
     * {@inheritDoc}
     */
    public function hasRate(string $from, string $to): bool
    {
        $from = strtoupper($from);
        $to   = strtoupper($to);

        if ($from === $to) {
            return true;
        }

        $cacheKey = self::CACHE_PREFIX . "{$from}_{$to}";
        if ($this->cache->has($cacheKey)) {
            return true;
        }

        return $this->inner->hasRate($from, $to);
    }

    /**
     * {@inheritDoc}
     */
    public function flush(): void
    {
        $this->inner->flush();
        // Cache সম্পূর্ণ ফ্লাশ করা যাবে না (prefix-based delete নেই),
        // তাই inner driver flush করাই যথেষ্ট। Production এ
        // cache tags ব্যবহার করে targeted flush করুন।
    }
}
