<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework — Phase 34 Test Suite
|--------------------------------------------------------------------------
|
| File      : scratch/test_phase34.php
| Module    : Currency
| Purpose   : Comprehensive tests for Money, Exchange Rate Drivers, CurrencyManager,
|             CLI commands, and global helpers.
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

require_once __DIR__ . '/../vendor/autoload.php';

use NetFusion\Core\Application;
use NetFusion\Core\Config\Config;
use NetFusion\Core\Currency\CurrencyManager;
use NetFusion\Core\Currency\Drivers\ArrayRateDriver;
use NetFusion\Core\Currency\Drivers\CacheRateDriver;
use NetFusion\Core\Currency\Drivers\DatabaseRateDriver;
use NetFusion\Core\Currency\Money;
use NetFusion\Core\Database\DatabaseManager;

$basePath = realpath(__DIR__ . '/..');
$app      = new Application($basePath);

// SQLite Test Database
$sqlitePath = __DIR__ . '/test_phase34.sqlite';
if (file_exists($sqlitePath)) {
    @unlink($sqlitePath);
}
touch($sqlitePath);

$app->make(Config::class)->set('database.default', 'sqlite');
$app->make(Config::class)->set('database.connections.sqlite.database', $sqlitePath);
$app->make(Config::class)->set('currency.default', 'array');

/** @var DatabaseManager $db */
$db   = $app->make(DatabaseManager::class);
$conn = $db->connection('sqlite');

// Migration চালাই
$migration = require __DIR__ . '/../database/migrations/2026_09_18_000017_create_currencies_and_exchange_rates_tables.php';
$migration->up($conn);

// Test runner
$passed = 0;
$failed = 0;
$total  = 0;

function t(bool $cond, string $name): void
{
    global $passed, $failed, $total;
    $total++;
    if ($cond) {
        $passed++;
        echo "  \033[32m✔\033[0m {$name}\n";
    } else {
        $failed++;
        echo "  \033[31m✖\033[0m {$name}\n";
    }
}

echo "\n=================================================================\n";
echo "  NetFusion Phase 34 — Currency, Money & FX Rate Engine Tests\n";
echo "=================================================================\n\n";

// =============================================================================
// Section 1: Money Value Object — Factory Methods
// =============================================================================
echo "\033[36m[Section 1: Money — Factory Methods]\033[0m\n";

$m1 = Money::of('100.50', 'USD');
t($m1->getMinorAmount() === 10050, 'Money::of("100.50", "USD") stores 10050 minor units');
t($m1->getCurrency() === 'USD', 'Money currency code is USD');
t($m1->getAmount() === '100.50', 'Money::getAmount() returns "100.50"');

$m2 = Money::ofMinor(500, 'BDT');
t($m2->getMinorAmount() === 500, 'Money::ofMinor(500, "BDT") stores 500 poysha');
t($m2->getAmount() === '5.00', 'Minor 500 poysha = 5.00 taka');

$mJPY = Money::of('1500', 'JPY');
t($mJPY->getMinorAmount() === 1500, 'JPY (0 decimal) stores exact units');
t($mJPY->getAmount() === '1500', 'JPY getAmount() returns "1500" without decimal');

$mZero = Money::zero('EUR');
t($mZero->isZero() === true, 'Money::zero() creates zero amount');
t($mZero->getMinorAmount() === 0, 'Zero Money has 0 minor units');

$mFloat = Money::fromFloat(99.99, 'USD');
t($mFloat->getMinorAmount() === 9999, 'Money::fromFloat(99.99) correctly stores 9999 cents');

// =============================================================================
// Section 2: Money Arithmetic
// =============================================================================
echo "\n\033[36m[Section 2: Money — Arithmetic Operations]\033[0m\n";

$a = Money::of('100.00', 'USD');
$b = Money::of('50.25', 'USD');

$sum = $a->add($b);
t($sum->getMinorAmount() === 15025, 'add(): $100.00 + $50.25 = $150.25 (15025 cents)');

$diff = $a->subtract($b);
t($diff->getMinorAmount() === 4975, 'subtract(): $100.00 - $50.25 = $49.75 (4975 cents)');

$doubled = $a->multiply(2);
t($doubled->getMinorAmount() === 20000, 'multiply(2): $100.00 * 2 = $200.00');

$half = $a->divide(2);
t($half->getMinorAmount() === 5000, 'divide(2): $100.00 / 2 = $50.00');

$tax = $a->percentage('7.5');
t($tax->getMinorAmount() === 750, 'percentage("7.5"): 7.5% of $100.00 = $7.50');

$neg = $a->negate();
t($neg->getMinorAmount() === -10000, 'negate(): -$100.00 minor units = -10000');
t($neg->abs()->getMinorAmount() === 10000, 'abs(): negative absolute = 10000');

// Immutability check
t($a->getMinorAmount() === 10000, 'Original Money unchanged after arithmetic (immutability)');

// Currency mismatch check
$caughtMismatch = false;
try {
    $a->add(Money::of('10.00', 'EUR'));
} catch (\RuntimeException $e) {
    $caughtMismatch = true;
}
t($caughtMismatch, 'Currency mismatch throws RuntimeException');

// =============================================================================
// Section 3: Money Comparison
// =============================================================================
echo "\n\033[36m[Section 3: Money — Comparison]\033[0m\n";

$x = Money::of('100.00', 'USD');
$y = Money::of('100.00', 'USD');
$z = Money::of('200.00', 'USD');

t($x->equals($y) === true,  'equals(): same amount and currency');
t($x->equals($z) === false, 'equals(): different amounts not equal');
t($z->greaterThan($x),      'greaterThan(): $200 > $100');
t($x->lessThan($z),         'lessThan(): $100 < $200');
t($x->greaterThanOrEqual($y), 'greaterThanOrEqual(): $100 >= $100');
t($x->lessThanOrEqual($y),    'lessThanOrEqual(): $100 <= $100');
t($x->isPositive(),           'isPositive(): $100 is positive');
t(Money::of('-10', 'USD')->isNegative(), 'isNegative(): -$10 is negative');

// =============================================================================
// Section 4: Money Formatting
// =============================================================================
echo "\n\033[36m[Section 4: Money — Formatting]\033[0m\n";

t(Money::of('100.50', 'USD')->format() === '$100.50', 'format(): USD $100.50');
t(Money::of('100.50', 'BDT')->format() === '৳100.50', 'format(): BDT ৳100.50');
t(Money::of('1500', 'JPY')->format() === '¥1,500', 'format(): JPY ¥1,500 (no decimal)');
t(Money::of('1000.00', 'EUR')->format() === '€1,000.00', 'format(): EUR €1,000.00 with thousands separator');
t(Money::of('50.75', 'USD')->toArray()['formatted'] === '$50.75', 'toArray()["formatted"] works correctly');
t((string) Money::of('75.00', 'GBP') === '£75.00', '__toString() returns formatted string');

// =============================================================================
// Section 5: ArrayRateDriver
// =============================================================================
echo "\n\033[36m[Section 5: ArrayRateDriver — In-Memory Rates]\033[0m\n";

$arrayDriver = new ArrayRateDriver([
    'USD_BDT' => '110.00',
    'USD_EUR' => '0.92',
    'EUR_USD' => '1.08',
    'USD_JPY' => '149.00',
]);

t($arrayDriver->hasRate('USD', 'BDT'), 'hasRate(): USD→BDT exists');
t(!$arrayDriver->hasRate('XYZ', 'ABC'), 'hasRate(): unknown pair returns false');
t($arrayDriver->getRate('USD', 'BDT') === '110.00', 'getRate(): USD→BDT = 110.00');
t($arrayDriver->getRate('USD', 'USD') === '1', 'getRate(): same currency = "1"');

$arrayDriver->setRate('GBP', 'USD', '1.26');
t($arrayDriver->hasRate('GBP', 'USD'), 'setRate(): GBP→USD set successfully');
t($arrayDriver->getRate('GBP', 'USD') === '1.26', 'getRate(): GBP→USD = 1.26');

$rates = $arrayDriver->getRates('USD');
t(isset($rates['BDT']) && $rates['BDT'] === '110.00', 'getRates("USD") contains BDT rate');
t(isset($rates['EUR']) && $rates['EUR'] === '0.92', 'getRates("USD") contains EUR rate');

$arrayDriver->flush();
$flushed = false;
try {
    $arrayDriver->getRate('USD', 'BDT');
} catch (\RuntimeException $e) {
    $flushed = true;
}
t($flushed, 'flush(): all rates cleared, getRate throws RuntimeException');

// =============================================================================
// Section 6: DatabaseRateDriver
// =============================================================================
echo "\n\033[36m[Section 6: DatabaseRateDriver — Database Rates]\033[0m\n";

$dbDriver = new DatabaseRateDriver($db);

// Migration এ USD→BDT seed ছিল
t($dbDriver->hasRate('USD', 'BDT'), 'DB: hasRate("USD", "BDT") from seed data');
t($dbDriver->getRate('USD', 'BDT') === '110.00', 'DB: getRate("USD", "BDT") = 110.00 from seed');

$dbDriver->setRate('GBP', 'BDT', '140.50', 'test');
t($dbDriver->hasRate('GBP', 'BDT'), 'DB: setRate creates GBP→BDT entry');
t($dbDriver->getRate('GBP', 'BDT') === '140.50', 'DB: getRate("GBP", "BDT") = 140.50');

$dbRates = $dbDriver->getRates('USD');
t(!empty($dbRates) && isset($dbRates['BDT']), 'DB: getRates("USD") returns BDT entry');

t($dbDriver->getRate('EUR', 'EUR') === '1', 'DB: same currency always returns "1"');

// =============================================================================
// Section 7: CacheRateDriver
// =============================================================================
echo "\n\033[36m[Section 7: CacheRateDriver — Cache-backed Decorator]\033[0m\n";

$cacheDriver   = new ArrayRateDriver(['USD_EUR' => '0.92', 'EUR_BDT' => '120.50']);
$cacheManager  = $app->make(\NetFusion\Core\Cache\CacheManager::class);
$cachedDriver  = new CacheRateDriver($cacheDriver, $cacheManager, 3600);

t($cachedDriver->getRate('USD', 'EUR') === '0.92', 'CacheDriver: first read from inner driver');
t($cachedDriver->getRate('USD', 'EUR') === '0.92', 'CacheDriver: second read from cache (same result)');

$cachedDriver->setRate('USD', 'CNY', '7.25', 'test');
t($cachedDriver->hasRate('USD', 'CNY'), 'CacheDriver: setRate writes to inner driver');

// =============================================================================
// Section 8: CurrencyManager
// =============================================================================
echo "\n\033[36m[Section 8: CurrencyManager — Orchestration]\033[0m\n";

$manager = new CurrencyManager($app);

// Custom array driver দিয়ে test করব
$manager->extend('test_array', function () {
    return new ArrayRateDriver([
        'USD_BDT' => '110.00',
        'USD_EUR' => '0.92',
        'EUR_USD' => '1.08',
        'USD_JPY' => '149.00',
        'JPY_USD' => '0.006711',
    ]);
});

// Driver resolution
$driver = $manager->driver('test_array');
t($driver instanceof ArrayRateDriver, 'CurrencyManager: driver() resolves custom driver');

// Conversion: USD → BDT
$usd100 = Money::of('100.00', 'USD');
$bdt    = $manager->convert($usd100, 'BDT', 'test_array');
t($bdt->getCurrency() === 'BDT', 'convert(): result currency is BDT');
t($bdt->getMinorAmount() === 1100000, 'convert(): $100.00 USD = ৳11,000.00 BDT (1100000 poysha)');

// Conversion: same currency (no-op)
$sameCcy = $manager->convert($usd100, 'USD', 'test_array');
t($sameCcy->equals($usd100), 'convert(): same currency returns identical Money');

// USD → JPY (0 decimal places)
$jpy = $manager->convert($usd100, 'JPY', 'test_array');
t($jpy->getCurrency() === 'JPY', 'convert(): USD→JPY result currency is JPY');
t($jpy->getMinorAmount() === 14900, 'convert(): $100 USD = ¥14,900 JPY (14900 units)');

// convertAmount shorthand
$bdt2 = $manager->convertAmount('50.00', 'USD', 'BDT', 'test_array');
t($bdt2->getMinorAmount() === 550000, 'convertAmount("50.00", "USD", "BDT"): 50 * 110 = 5500.00 BDT');

// Rate management proxy
$manager->setRate('USD', 'INR', '83.00', 'test', 'test_array');
t($manager->getRate('USD', 'INR', 'test_array') === '83.00', 'setRate + getRate proxy works');
t($manager->hasRate('USD', 'INR', 'test_array') === true, 'hasRate proxy works');

// Format proxy
$formatted = $manager->format(Money::of('2500.75', 'BDT'));
t($formatted === '৳2,500.75', 'format(): CurrencyManager formats BDT correctly');

// =============================================================================
// Section 9: Container Integration
// =============================================================================
echo "\n\033[36m[Section 9: Container & Application Integration]\033[0m\n";

$appManager = $app->make(\NetFusion\Core\Currency\CurrencyManager::class);
t($appManager instanceof CurrencyManager, 'CurrencyManager::class singleton resolved from container');

$appAlias = $app->make('currency');
t($appAlias instanceof CurrencyManager, '"currency" alias singleton resolved from container');

// =============================================================================
// Section 10: Global Helpers
// =============================================================================
echo "\n\033[36m[Section 10: Global Helpers — money() and currency()]\033[0m\n";

t(function_exists('money'), 'money() helper function exists');
t(function_exists('currency'), 'currency() helper function exists');

$helperMoney = money('150.00', 'USD');
t($helperMoney instanceof Money, 'money("150.00", "USD") returns Money instance');
t($helperMoney->getMinorAmount() === 15000, 'money() creates correct minor units');

$helperMoneyMinor = money(5000, 'BDT');
t($helperMoneyMinor instanceof Money, 'money(5000, "BDT") from integer minor units');
t($helperMoneyMinor->getAmount() === '50.00', 'money(5000, "BDT") = 50.00 taka');

$currencyManager = currency();
t($currencyManager instanceof CurrencyManager, 'currency() returns CurrencyManager instance');

// =============================================================================
// Section 11: Migration Schema Verification
// =============================================================================
echo "\n\033[36m[Section 11: Database Schema Verification]\033[0m\n";

$currRow = $conn->selectOne("SELECT * FROM currencies WHERE code = 'USD'");
t($currRow !== null, 'currencies table: USD row seeded');
t($currRow['decimal_places'] == 2, 'currencies.decimal_places = 2 for USD');
t($currRow['is_active'] == 1, 'currencies.is_active = 1 (default active)');

$jpyRow = $conn->selectOne("SELECT * FROM currencies WHERE code = 'JPY'");
t($jpyRow !== null && $jpyRow['decimal_places'] == 0, 'currencies.decimal_places = 0 for JPY');

$bdtRow = $conn->selectOne("SELECT * FROM currencies WHERE code = 'BDT'");
t($bdtRow !== null, 'currencies table: BDT row seeded');
t($bdtRow['symbol'] === '৳', 'currencies.symbol = ৳ for BDT');

$rateRow = $conn->selectOne("SELECT * FROM exchange_rates WHERE from_currency = 'USD' AND to_currency = 'BDT'");
t($rateRow !== null && $rateRow['rate'] === '110.00', 'exchange_rates: USD→BDT seed rate = 110.00');
t($rateRow['source'] === 'seed', 'exchange_rates.source = "seed" for seeded data');

// =============================================================================
// Section 12: Edge Cases & Financial Accuracy
// =============================================================================
echo "\n\033[36m[Section 12: Edge Cases & Financial Accuracy]\033[0m\n";

// Large number accuracy
$large = Money::of('999999999.99', 'USD');
t($large->getMinorAmount() === 99999999999, 'Large amount: $999,999,999.99 = 99999999999 cents');

// Negative amount
$negMoney = Money::of('-50.00', 'USD');
t($negMoney->getMinorAmount() === -5000, 'Negative amount: -$50.00 = -5000 cents');

// Division rounding (1/3)
$oneThird = Money::of('10.00', 'USD')->divide(3);
t($oneThird->getMinorAmount() === 333, 'divide(3): $10.00 / 3 = $3.33 (333 cents, not 333.33...)');

// Zero operations
$zeroAdd = Money::zero('USD')->add(Money::of('25.00', 'USD'));
t($zeroAdd->getMinorAmount() === 2500, 'zero()->add(): $0 + $25 = $25');

// Invalid currency code
$invalidCurrency = false;
try {
    Money::of('100', 'US'); // Invalid: 2 chars
} catch (\InvalidArgumentException $e) {
    $invalidCurrency = true;
}
t($invalidCurrency, 'Invalid currency code (2 chars) throws InvalidArgumentException');

// Divide by zero
$divZero = false;
try {
    Money::of('100', 'USD')->divide(0);
} catch (\InvalidArgumentException $e) {
    $divZero = true;
}
t($divZero, 'divide(0) throws InvalidArgumentException');

// toArray() structure
$arr = Money::of('100.50', 'USD')->toArray();
t(
    isset($arr['amount'], $arr['minor_amount'], $arr['currency'], $arr['formatted']),
    'toArray() returns all required keys: amount, minor_amount, currency, formatted'
);
t($arr['amount'] === '100.50' && $arr['minor_amount'] === 10050 && $arr['currency'] === 'USD', 'toArray() values are correct');

// Custom currency registration
Money::registerCurrency('XBT', 8); // Bitcoin-like: 8 decimal places
$btc = Money::of('0.00100000', 'XBT');
t(Money::decimalPlacesFor('XBT') === 8, 'registerCurrency("XBT", 8) stores decimal places');
t($btc->getMinorAmount() === 100000, 'Custom 8-decimal currency: 0.001 XBT = 100000 satoshi');

// =============================================================================
// Cleanup & Summary
// =============================================================================
if (file_exists($sqlitePath)) {
    @unlink($sqlitePath);
}

echo "\n=================================================================\n";
echo "  Phase 34 Results: {$passed}/{$total} Passed";
if ($failed > 0) {
    echo " | \033[31m{$failed} Failed\033[0m";
} else {
    echo " \033[32m(100%)\033[0m";
}
echo "\n=================================================================\n\n";

exit($failed > 0 ? 1 : 0);
