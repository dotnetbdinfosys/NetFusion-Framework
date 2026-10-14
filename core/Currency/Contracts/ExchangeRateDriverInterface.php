<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Currency/Contracts/ExchangeRateDriverInterface.php
| Module    : Currency
| Namespace : NetFusion\Core\Currency\Contracts
| Purpose   : Contract for all exchange rate storage and retrieval drivers.
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

namespace NetFusion\Core\Currency\Contracts;

/**
 * ExchangeRateDriverInterface
 *
 * প্রতিটি exchange rate driver এই contract implement করবে।
 * সকল rate BCMath-safe string হিসেবে store ও return হবে।
 */
interface ExchangeRateDriverInterface
{
    /**
     * দুটি কারেন্সির মধ্যে বর্তমান exchange rate পান।
     * রেট পাওয়া না গেলে \RuntimeException throw হবে।
     *
     * @param  string $from Source currency code (e.g. 'USD')
     * @param  string $to   Target currency code (e.g. 'BDT')
     * @return string       BCMath-safe decimal string (e.g. '110.00')
     *
     * @throws \RuntimeException যদি rate পাওয়া না যায়
     */
    public function getRate(string $from, string $to): string;

    /**
     * একটি নতুন exchange rate সেট করুন বা আপডেট করুন।
     *
     * @param  string      $from   Source currency code
     * @param  string      $to     Target currency code
     * @param  string      $rate   BCMath-safe decimal string
     * @param  string|null $source Rate source (e.g. 'api', 'manual', 'seed')
     */
    public function setRate(string $from, string $to, string $rate, ?string $source = 'manual'): void;

    /**
     * একটি base currency থেকে সমস্ত available rates পান।
     *
     * @param  string $base Base currency code (e.g. 'USD')
     * @return array<string, string> ['EUR' => '0.92', 'BDT' => '110.00', ...]
     */
    public function getRates(string $base): array;

    /**
     * দুটি কারেন্সির মধ্যে rate বিদ্যমান কিনা চেক করুন।
     *
     * @param  string $from Source currency code
     * @param  string $to   Target currency code
     * @return bool
     */
    public function hasRate(string $from, string $to): bool;

    /**
     * সব cached/stored rates ফ্লাশ করুন।
     */
    public function flush(): void;
}
