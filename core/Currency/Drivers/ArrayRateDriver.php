<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Currency/Drivers/ArrayRateDriver.php
| Module    : Currency
| Namespace : NetFusion\Core\Currency\Drivers
| Purpose   : In-memory exchange rate driver for testing and prototyping.
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
use RuntimeException;

/**
 * ArrayRateDriver — In-Memory Exchange Rate Store
 *
 * টেস্টিং ও লোকাল ডেভেলপমেন্টের জন্য। Production এ DatabaseRateDriver ব্যবহার করুন।
 * সকল rate BCMath-safe string হিসেবে সংরক্ষিত।
 */
class ArrayRateDriver implements ExchangeRateDriverInterface
{
    /**
     * Stored rates: ['FROM_TO' => ['rate' => '1.23', 'source' => 'manual']]
     *
     * @var array<string, array{rate: string, source: string}>
     */
    private array $rates = [];

    /**
     * Pre-seeded rates দিয়ে driver তৈরি করুন।
     *
     * @param array<string, string> $initialRates ['USD_BDT' => '110.00', ...]
     */
    public function __construct(array $initialRates = [])
    {
        foreach ($initialRates as $pair => $rate) {
            $this->rates[$pair] = ['rate' => (string) $rate, 'source' => 'init'];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getRate(string $from, string $to): string
    {
        $from = strtoupper($from);
        $to   = strtoupper($to);

        // Same currency: rate সর্বদা 1
        if ($from === $to) {
            return '1';
        }

        $key = "{$from}_{$to}";

        if (!isset($this->rates[$key])) {
            // Cross-rate calculate করার চেষ্টা করি USD base দিয়ে
            $crossRate = $this->tryComputeCrossRate($from, $to);
            if ($crossRate !== null) {
                return $crossRate;
            }
            throw new RuntimeException("Exchange rate not found: {$from} → {$to}");
        }

        return $this->rates[$key]['rate'];
    }

    /**
     * {@inheritDoc}
     */
    public function setRate(string $from, string $to, string $rate, ?string $source = 'manual'): void
    {
        $key = strtoupper($from) . '_' . strtoupper($to);
        $this->rates[$key] = [
            'rate'   => $rate,
            'source' => $source ?? 'manual',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getRates(string $base): array
    {
        $base   = strtoupper($base);
        $result = [];

        foreach ($this->rates as $pair => $data) {
            [$from, $to] = explode('_', $pair, 2);
            if ($from === $base) {
                $result[$to] = $data['rate'];
            }
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

        return isset($this->rates["{$from}_{$to}"]);
    }

    /**
     * {@inheritDoc}
     */
    public function flush(): void
    {
        $this->rates = [];
    }

    /**
     * USD base দিয়ে cross-rate calculate করার চেষ্টা।
     * যেমন: EUR→BDT = (1/USD→EUR) * USD→BDT
     */
    private function tryComputeCrossRate(string $from, string $to): ?string
    {
        // from_USD এবং to_USD দিয়ে cross
        $fromUsdKey = "{$from}_USD";
        $usdToKey   = "USD_{$to}";

        if (isset($this->rates[$fromUsdKey]) && isset($this->rates[$usdToKey])) {
            if (extension_loaded('bcmath')) {
                return bcmul($this->rates[$fromUsdKey]['rate'], $this->rates[$usdToKey]['rate'], 8);
            }
            return (string) round(
                (float) $this->rates[$fromUsdKey]['rate'] * (float) $this->rates[$usdToKey]['rate'],
                8
            );
        }

        // USD_from এবং USD_to দিয়ে cross
        $usdFromKey = "USD_{$from}";
        $usdToKey2  = "USD_{$to}";

        if (isset($this->rates[$usdFromKey]) && isset($this->rates[$usdToKey2])) {
            $fromUsdRate = $this->rates[$usdFromKey]['rate'];
            $toUsdRate   = $this->rates[$usdToKey2]['rate'];

            if ((float) $fromUsdRate === 0.0) {
                return null;
            }

            if (extension_loaded('bcmath')) {
                return bcdiv($toUsdRate, $fromUsdRate, 8);
            }
            return (string) round((float) $toUsdRate / (float) $fromUsdRate, 8);
        }

        return null;
    }
}
