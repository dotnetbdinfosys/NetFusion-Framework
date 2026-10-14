<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/CurrencyUpdateRatesCommand.php
| Module    : Currency
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : CLI command to manually update exchange rates in the database.
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

/**
 * CurrencyUpdateRatesCommand
 *
 * Usage:
 *   php netfusion currency:update-rates --from=USD --to=BDT --rate=110.50
 *   php netfusion currency:update-rates --from=USD --to=EUR --rate=0.92 --source=api
 *   php netfusion currency:update-rates --list  (সব available rates দেখান)
 */
class CurrencyUpdateRatesCommand extends Command
{
    protected string $name        = 'currency:update-rates';
    protected string $description = 'Manually update or list exchange rates in the database.';

    public function handle(): int
    {
        // --list option দিয়ে সব rates দেখানো
        if ($this->option('list')) {
            return $this->listRates();
        }

        $from   = $this->option('from');
        $to     = $this->option('to');
        $rate   = $this->option('rate');
        $source = $this->option('source') ?: 'manual';

        if (!$from || !$to || !$rate) {
            $this->error('Usage: currency:update-rates --from=USD --to=BDT --rate=110.50 [--source=api]');
            return 1;
        }

        $from = strtoupper((string) $from);
        $to   = strtoupper((string) $to);
        $rate = (string) $rate;

        // Rate validation: positive numeric string হতে হবে
        if (!is_numeric($rate) || (float) $rate <= 0) {
            $this->error("Invalid rate value: [{$rate}]. Must be a positive decimal number.");
            return 1;
        }

        /** @var CurrencyManager $manager */
        $manager = app('currency');

        try {
            $manager->setRate($from, $to, $rate, (string) $source, 'database');
            $this->info("✔ Exchange rate updated: {$from} → {$to} = {$rate} (source: {$source})");

            // Auto-reverse rate যদি config enable থাকে
            if (config('currency.auto_reverse', true)) {
                if (extension_loaded('bcmath')) {
                    $reverseRate = bcdiv('1', $rate, 10);
                } else {
                    $reverseRate = (string) round(1 / (float) $rate, 10);
                }

                $manager->setRate($to, $from, $reverseRate, $source . '_reverse', 'database');
                $this->info("✔ Reverse rate computed: {$to} → {$from} = {$reverseRate}");
            }
        } catch (\Exception $e) {
            $this->error('Failed to update rate: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * সব exchange rates তালিকা প্রদর্শন করুন।
     */
    private function listRates(): int
    {
        /** @var CurrencyManager $manager */
        $manager = app('currency');

        $currencies = ['USD', 'EUR', 'GBP', 'BDT', 'JPY', 'INR'];
        $hasAny     = false;

        $this->info('');
        $this->info('=== Exchange Rates (Database) ===');
        $this->info('');

        foreach ($currencies as $base) {
            try {
                $rates = $manager->getRates($base, 'database');
                if (!empty($rates)) {
                    $hasAny = true;
                    $this->info("  Base: {$base}");
                    foreach ($rates as $to => $rate) {
                        $this->info("    → {$to}: {$rate}");
                    }
                    $this->info('');
                }
            } catch (\Exception) {
                // Skip currencies with no rates
            }
        }

        if (!$hasAny) {
            $this->info('No exchange rates found. Use --from --to --rate to add rates.');
        }

        return 0;
    }
}
