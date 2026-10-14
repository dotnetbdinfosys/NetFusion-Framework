<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Currency/Drivers/DatabaseRateDriver.php
| Module    : Currency
| Namespace : NetFusion\Core\Currency\Drivers
| Purpose   : Database-backed exchange rate driver using exchange_rates table.
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

use NetFusion\Core\Currency\Contracts\ExchangeRateDriverInterface;
use NetFusion\Core\Database\DatabaseManager;
use RuntimeException;

/**
 * DatabaseRateDriver — Database-backed Exchange Rate Storage
 *
 * exchange_rates টেবিল থেকে rate পড়ে এবং লেখে।
 * সর্বশেষ valid_at অনুযায়ী সবচেয়ে নতুন rate ব্যবহার করা হয়।
 */
class DatabaseRateDriver implements ExchangeRateDriverInterface
{
    /**
     * In-memory cache (একটি request জীবনকালীন)
     * @var array<string, string>
     */
    private array $cache = [];

    public function __construct(
        private readonly DatabaseManager $db,
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

        $cacheKey = "{$from}_{$to}";

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $row = $this->db->connection()->selectOne(
            "SELECT rate FROM exchange_rates
             WHERE from_currency = ? AND to_currency = ?
             ORDER BY valid_at DESC LIMIT 1",
            [$from, $to]
        );

        if ($row === null) {
            throw new RuntimeException("Exchange rate not found in database: {$from} → {$to}");
        }

        $rate = (string) $row['rate'];
        $this->cache[$cacheKey] = $rate;
        return $rate;
    }

    /**
     * {@inheritDoc}
     */
    public function setRate(string $from, string $to, string $rate, ?string $source = 'manual'): void
    {
        $from    = strtoupper($from);
        $to      = strtoupper($to);
        $validAt = date('Y-m-d H:i:s');
        $now     = $validAt;

        $this->db->connection()->statement(
            "INSERT OR REPLACE INTO exchange_rates
                (from_currency, to_currency, rate, source, valid_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$from, $to, $rate, $source ?? 'manual', $validAt, $now]
        );

        // Cache পরিষ্কার করি যেন পরবর্তী read নতুন rate পায়
        unset($this->cache["{$from}_{$to}"]);
    }

    /**
     * {@inheritDoc}
     */
    public function getRates(string $base): array
    {
        $base = strtoupper($base);

        $rows = $this->db->connection()->select(
            "SELECT to_currency, rate
             FROM exchange_rates
             WHERE from_currency = ?
             GROUP BY to_currency
             HAVING valid_at = MAX(valid_at)",
            [$base]
        );

        $result = [];
        foreach ($rows as $row) {
            $result[$row['to_currency']] = (string) $row['rate'];
        }

        return $result;
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

        $cacheKey = "{$from}_{$to}";
        if (isset($this->cache[$cacheKey])) {
            return true;
        }

        $row = $this->db->connection()->selectOne(
            "SELECT 1 FROM exchange_rates WHERE from_currency = ? AND to_currency = ? LIMIT 1",
            [$from, $to]
        );

        return $row !== null;
    }

    /**
     * {@inheritDoc}
     */
    public function flush(): void
    {
        $this->cache = [];
    }
}
