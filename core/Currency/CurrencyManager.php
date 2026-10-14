<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Currency/CurrencyManager.php
| Module    : Currency
| Namespace : NetFusion\Core\Currency
| Purpose   : Enterprise multi-currency manager — conversion, formatting,
|             driver management, and FX rate orchestration.
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

namespace NetFusion\Core\Currency;

use Closure;
use InvalidArgumentException;
use NetFusion\Core\Application;
use NetFusion\Core\Currency\Contracts\ExchangeRateDriverInterface;
use NetFusion\Core\Currency\Drivers\ArrayRateDriver;
use NetFusion\Core\Currency\Drivers\CacheRateDriver;
use NetFusion\Core\Currency\Drivers\DatabaseRateDriver;
use RuntimeException;

/**
 * CurrencyManager
 *
 * মাল্টি-কারেন্সি অপারেশনের কেন্দ্রীয় orchestrator।
 * Responsibilities:
 * - Exchange rate driver resolution (database, cache, array)
 * - Money object conversion (Currency A → Currency B)
 * - Human-readable formatting
 * - Custom driver extension point
 */
class CurrencyManager
{
    /**
     * Registered driver factories
     * @var array<string, Closure>
     */
    private array $drivers = [];

    /**
     * Resolved driver instances
     * @var array<string, ExchangeRateDriverInterface>
     */
    private array $resolved = [];

    /**
     * Custom extension drivers
     * @var array<string, Closure>
     */
    private array $customDrivers = [];

    public function __construct(
        private readonly ?Application $app = null,
    ) {
        $this->registerDefaultDriverFactories();
    }

    // =========================================================================
    //  Driver Resolution
    // =========================================================================

    /**
     * একটি নির্দিষ্ট exchange rate driver রিটার্ন করুন।
     *
     * @param  string|null $name Driver name (null হলে config থেকে default নেওয়া হয়)
     */
    public function driver(?string $name = null): ExchangeRateDriverInterface
    {
        $name = $name ?? $this->getDefaultDriver();

        if (isset($this->resolved[$name])) {
            return $this->resolved[$name];
        }

        // Custom driver চেক করি
        if (isset($this->customDrivers[$name])) {
            $this->resolved[$name] = ($this->customDrivers[$name])($this->app);
            return $this->resolved[$name];
        }

        if (!isset($this->drivers[$name])) {
            throw new InvalidArgumentException("Currency driver [{$name}] is not registered.");
        }

        $this->resolved[$name] = ($this->drivers[$name])();
        return $this->resolved[$name];
    }

    /**
     * Custom driver রেজিস্টার করুন।
     *
     * @param  string  $name    Driver alias name
     * @param  Closure $factory Driver factory Closure (receives ?Application)
     */
    public function extend(string $name, Closure $factory): static
    {
        $this->customDrivers[$name] = $factory;
        unset($this->resolved[$name]);
        return $this;
    }

    // =========================================================================
    //  Conversion
    // =========================================================================

    /**
     * একটি Money object কে অন্য কারেন্সিতে convert করুন।
     *
     * @param  Money       $money      Source Money object
     * @param  string      $toCurrency Target currency ISO 4217 code
     * @param  string|null $driver     Driver name (null = default)
     *
     * @throws RuntimeException rate পাওয়া না গেলে
     */
    public function convert(Money $money, string $toCurrency, ?string $driver = null): Money
    {
        $toCurrency = strtoupper($toCurrency);

        // Same currency হলে conversion এর দরকার নেই
        if ($money->getCurrency() === $toCurrency) {
            return $money;
        }

        $rate = $this->driver($driver)->getRate($money->getCurrency(), $toCurrency);

        // Conversion: BCMath দিয়ে precision বজায় রাখা হয়
        // source minor units → source decimal → multiply by rate → target decimal → target minor units
        $fromDp = Money::decimalPlacesFor($money->getCurrency());
        $toDp   = Money::decimalPlacesFor($toCurrency);

        if (extension_loaded('bcmath')) {
            $fromDecimal    = bcdiv((string) $money->getMinorAmount(), bcpow('10', (string) $fromDp, 0), 10);
            $convertedDecimal = bcmul($fromDecimal, $rate, 10);
            $toMinorUnits   = (int) bcmul($convertedDecimal, bcpow('10', (string) $toDp, 0), 0);
        } else {
            $fromDecimal      = $money->getMinorAmount() / (10 ** $fromDp);
            $convertedDecimal = $fromDecimal * (float) $rate;
            $toMinorUnits     = (int) round($convertedDecimal * (10 ** $toDp));
        }

        return Money::ofMinor($toMinorUnits, $toCurrency);
    }

    /**
     * একটি decimal amount directly convert করুন (Money object ছাড়া)।
     *
     * @param  string      $amount     BCMath-safe decimal string
     * @param  string      $from       Source currency code
     * @param  string      $to         Target currency code
     * @param  string|null $driver     Driver name (null = default)
     */
    public function convertAmount(string $amount, string $from, string $to, ?string $driver = null): Money
    {
        $money = Money::of($amount, $from);
        return $this->convert($money, $to, $driver);
    }

    // =========================================================================
    //  Formatting
    // =========================================================================

    /**
     * Money object কে human-readable string এ format করুন।
     *
     * @param  Money       $money  Money object
     * @param  string|null $symbol Custom symbol override
     */
    public function format(Money $money, ?string $symbol = null): string
    {
        return $money->format($symbol);
    }

    // =========================================================================
    //  Rate Management (Proxy to driver)
    // =========================================================================

    /**
     * Exchange rate সেট করুন।
     */
    public function setRate(string $from, string $to, string $rate, ?string $source = 'manual', ?string $driver = null): void
    {
        $this->driver($driver)->setRate($from, $to, $rate, $source);
    }

    /**
     * Exchange rate পান।
     */
    public function getRate(string $from, string $to, ?string $driver = null): string
    {
        return $this->driver($driver)->getRate($from, $to);
    }

    /**
     * Base currency থেকে সব rates পান।
     */
    public function getRates(string $base, ?string $driver = null): array
    {
        return $this->driver($driver)->getRates($base);
    }

    /**
     * Rate বিদ্যমান কিনা চেক করুন।
     */
    public function hasRate(string $from, string $to, ?string $driver = null): bool
    {
        return $this->driver($driver)->hasRate($from, $to);
    }

    // =========================================================================
    //  Internal
    // =========================================================================

    /**
     * Default driver factories রেজিস্টার করুন।
     */
    private function registerDefaultDriverFactories(): void
    {
        // Array driver (in-memory, tests)
        $this->drivers['array'] = function () {
            return new ArrayRateDriver();
        };

        // Database driver
        $this->drivers['database'] = function () {
            if ($this->app === null) {
                throw new RuntimeException('Application instance required for database driver.');
            }
            $db = $this->app->make(\NetFusion\Core\Database\DatabaseManager::class);
            return new DatabaseRateDriver($db);
        };

        // Cache driver (wraps database driver)
        $this->drivers['cache'] = function () {
            if ($this->app === null) {
                throw new RuntimeException('Application instance required for cache driver.');
            }
            $db    = $this->app->make(\NetFusion\Core\Database\DatabaseManager::class);
            $inner = new DatabaseRateDriver($db);

            if ($this->app->has('cache')) {
                $cache = $this->app->make(\NetFusion\Core\Cache\CacheManager::class);
                $ttl   = $this->getConfig('currency.cache_ttl', 3600);
                return new CacheRateDriver($inner, $cache, (int) $ttl);
            }

            return $inner;
        };
    }

    /**
     * Default driver name পান।
     */
    private function getDefaultDriver(): string
    {
        if ($this->app !== null && $this->app->has('config')) {
            return (string) $this->app->make(\NetFusion\Core\Config\Config::class)
                ->get('currency.default', 'array');
        }

        return 'array';
    }

    /**
     * Config value পান।
     */
    private function getConfig(string $key, mixed $default = null): mixed
    {
        if ($this->app !== null && $this->app->has('config')) {
            return $this->app->make(\NetFusion\Core\Config\Config::class)->get($key, $default);
        }

        return $default;
    }
}
