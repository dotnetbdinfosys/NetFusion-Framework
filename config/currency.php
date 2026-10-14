<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework — Currency Configuration
|--------------------------------------------------------------------------
|
| File      : config/currency.php
| Module    : Currency
| Purpose   : Multi-currency, FX rates, and Money formatting configuration.
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

return [

    /*
    |--------------------------------------------------------------------------
    | Default Exchange Rate Driver
    |--------------------------------------------------------------------------
    |
    | Supported: "array", "database", "cache"
    |   - array:    In-memory (testing/local development)
    |   - database: exchange_rates টেবিল থেকে পড়ে
    |   - cache:    Database driver-এর উপর cache layer
    |
    */
    'default' => env('CURRENCY_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Base / System Currency
    |--------------------------------------------------------------------------
    |
    | সমস্ত cross-rate calculation এই base currency দিয়ে করা হয়।
    |
    */
    'base' => env('CURRENCY_BASE', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Cache TTL (seconds)
    |--------------------------------------------------------------------------
    |
    | cache driver ব্যবহার করলে rate কতক্ষণ cache এ থাকবে।
    | Default: 3600 (1 ঘণ্টা)
    |
    */
    'cache_ttl' => (int) env('CURRENCY_CACHE_TTL', 3600),

    /*
    |--------------------------------------------------------------------------
    | Supported Currencies
    |--------------------------------------------------------------------------
    |
    | এখানে শুধুমাত্র system-wide enabled currencies তালিকাবদ্ধ।
    | Full list database currencies টেবিলে থাকে।
    |
    */
    'supported' => [
        'BDT', 'USD', 'EUR', 'GBP', 'JPY',
        'CAD', 'AUD', 'CHF', 'CNY', 'INR',
        'SAR', 'AED', 'SGD', 'MYR',
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency Symbols
    |--------------------------------------------------------------------------
    |
    | ISO 4217 → display symbol mapping।
    | Money::format() এ use হয়।
    |
    */
    'symbols' => [
        'BDT' => '৳',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'JPY' => '¥',
        'CAD' => 'CA$',
        'AUD' => 'A$',
        'CHF' => 'Fr',
        'CNY' => '¥',
        'INR' => '₹',
        'SAR' => '﷼',
        'AED' => 'د.إ',
        'SGD' => 'S$',
        'MYR' => 'RM',
    ],

    /*
    |--------------------------------------------------------------------------
    | Decimal Places per Currency
    |--------------------------------------------------------------------------
    |
    | ISO 4217 minor units কনভেনশন।
    | এখানে না থাকলে default 2 ধরা হয়।
    |
    */
    'decimal_places' => [
        'JPY' => 0,
        'KWD' => 3,
        'BHD' => 3,
        'OMR' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-set Reverse Rate
    |--------------------------------------------------------------------------
    |
    | true হলে USD→BDT সেট করলে BDT→USD স্বয়ংক্রিয়ভাবে compute হয়।
    |
    */
    'auto_reverse' => env('CURRENCY_AUTO_REVERSE', true),

];
