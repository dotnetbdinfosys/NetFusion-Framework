<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : database/migrations/2026_09_18_000017_create_currencies_and_exchange_rates_tables.php
| Module    : Currency
| Namespace : N/A (Migration)
| Purpose   : Creates the currencies and exchange_rates tables for multi-currency support.
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

use NetFusion\Core\Database\Connection;

return new class {
    /**
     * Run the migration — create currencies and exchange_rates tables.
     */
    public function up(Connection $conn): void
    {
        $conn->statement("
            CREATE TABLE IF NOT EXISTS currencies (
                code           TEXT    NOT NULL PRIMARY KEY,
                name           TEXT    NOT NULL,
                symbol         TEXT    NOT NULL DEFAULT '',
                decimal_places INTEGER NOT NULL DEFAULT 2,
                is_active      INTEGER NOT NULL DEFAULT 1,
                created_at     TEXT    NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S', 'now')),
                updated_at     TEXT    NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S', 'now'))
            )
        ");

        $defaultCurrencies = [
            ['BDT', 'Bangladeshi Taka',  '৳',  2],
            ['USD', 'US Dollar',         '$',  2],
            ['EUR', 'Euro',              '€',  2],
            ['GBP', 'British Pound',     '£',  2],
            ['JPY', 'Japanese Yen',      '¥',  0],
            ['CAD', 'Canadian Dollar',   'CA$',2],
            ['AUD', 'Australian Dollar', 'A$', 2],
            ['CHF', 'Swiss Franc',       'Fr', 2],
            ['CNY', 'Chinese Yuan',      '¥',  2],
            ['INR', 'Indian Rupee',      '₹',  2],
            ['SAR', 'Saudi Riyal',       '﷼',  2],
            ['AED', 'UAE Dirham',        'د.إ',2],
            ['SGD', 'Singapore Dollar',  'S$', 2],
            ['MYR', 'Malaysian Ringgit', 'RM', 2],
        ];

        foreach ($defaultCurrencies as [$code, $name, $symbol, $dp]) {
            $conn->statement(
                "INSERT OR IGNORE INTO currencies (code, name, symbol, decimal_places) VALUES (?, ?, ?, ?)",
                [$code, $name, $symbol, $dp]
            );
        }

        $conn->statement("
            CREATE TABLE IF NOT EXISTS exchange_rates (
                id            INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
                from_currency TEXT    NOT NULL,
                to_currency   TEXT    NOT NULL,
                rate          TEXT    NOT NULL,
                source        TEXT    NOT NULL DEFAULT 'manual',
                valid_at      TEXT    NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S', 'now')),
                created_at    TEXT    NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S', 'now'))
            )
        ");

        $conn->statement("
            CREATE UNIQUE INDEX IF NOT EXISTS exchange_rates_pair_valid_at
                ON exchange_rates (from_currency, to_currency, valid_at)
        ");

        $now = date('Y-m-d H:i:s');
        $seeds = [
            ['USD', 'BDT', '110.00',     'seed'],
            ['BDT', 'USD', '0.00909090', 'seed'],
            ['USD', 'EUR', '0.92',       'seed'],
            ['EUR', 'USD', '1.08',       'seed'],
            ['USD', 'GBP', '0.79',       'seed'],
            ['GBP', 'USD', '1.26',       'seed'],
            ['USD', 'JPY', '149.00',     'seed'],
            ['JPY', 'USD', '0.00671',    'seed'],
            ['USD', 'INR', '83.00',      'seed'],
            ['INR', 'USD', '0.01204',    'seed'],
        ];

        foreach ($seeds as [$from, $to, $rate, $source]) {
            $conn->statement(
                "INSERT OR IGNORE INTO exchange_rates (from_currency, to_currency, rate, source, valid_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [$from, $to, $rate, $source, $now, $now]
            );
        }
    }

    public function down(Connection $conn): void
    {
        $conn->statement('DROP TABLE IF EXISTS exchange_rates');
        $conn->statement('DROP TABLE IF EXISTS currencies');
    }
};
