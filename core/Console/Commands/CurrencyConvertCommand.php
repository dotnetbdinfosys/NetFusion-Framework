<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/CurrencyConvertCommand.php
| Module    : Currency
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : CLI command for live currency conversion with formatting.
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

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;
use NetFusion\Core\Currency\CurrencyManager;
use NetFusion\Core\Currency\Money;

/**
 * CurrencyConvertCommand
 *
 * Usage:
 *   php netfusion currency:convert 100.00 USD BDT
 *   php netfusion currency:convert 50 EUR USD --driver=array
 *   php netfusion currency:convert 1000 BDT JPY
 */
class CurrencyConvertCommand extends Command
{
    protected string $name        = 'currency:convert';
    protected string $description = 'Convert an amount from one currency to another using stored exchange rates.';

    public function handle(): int
    {
        $args = $this->arguments();

        if (count($args) < 3) {
            $this->error('Usage: currency:convert <amount> <from_currency> <to_currency> [--driver=database]');
            $this->info('Example: php netfusion currency:convert 100 USD BDT');
            return 1;
        }

        $amountStr  = $args[0];
        $fromCcy    = strtoupper($args[1]);
        $toCcy      = strtoupper($args[2]);
        $driver     = $this->option('driver') ?: null;

        // Validation
        if (!is_numeric($amountStr)) {
            $this->error("Invalid amount: [{$amountStr}]. Must be a numeric value.");
            return 1;
        }

        if (strlen($fromCcy) !== 3 || strlen($toCcy) !== 3) {
            $this->error("Invalid currency code. Must be 3-letter ISO 4217 codes (e.g. USD, BDT, EUR).");
            return 1;
        }

        /** @var CurrencyManager $manager */
        $manager = app('currency');

        try {
            $sourceMoney    = Money::of($amountStr, $fromCcy);
            $convertedMoney = $manager->convert($sourceMoney, $toCcy, $driver ? (string) $driver : null);
            $rate           = $manager->getRate($fromCcy, $toCcy, $driver ? (string) $driver : null);

            $this->info('');
            $this->info('╔══════════════════════════════════════════╗');
            $this->info('║        NetFusion Currency Converter       ║');
            $this->info('╚══════════════════════════════════════════╝');
            $this->info('');
            $this->info("  From  : {$sourceMoney->format()} ({$fromCcy})");
            $this->info("  Rate  : 1 {$fromCcy} = {$rate} {$toCcy}");
            $this->info("  To    : {$convertedMoney->format()} ({$toCcy})");
            $this->info('');
            $this->info("  Minor Units: {$convertedMoney->getMinorAmount()} (integer, no float precision loss)");
            $this->info('');
        } catch (\RuntimeException $e) {
            $this->error('Conversion failed: ' . $e->getMessage());
            $this->info('Hint: Make sure the exchange rate exists. Use `currency:update-rates` to add rates.');
            return 1;
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
