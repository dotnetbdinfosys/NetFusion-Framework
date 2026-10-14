<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Translation/Contracts/TranslatorInterface.php
| Module    : Core\Translation
| Namespace : NetFusion\Core\Translation\Contracts
| Purpose   : Interface contract for internationalization and localization translators.
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

namespace NetFusion\Core\Translation\Contracts;

interface TranslatorInterface
{
    /**
     * Get the translation for the given key.
     *
     * @param  string               $key
     * @param  array<string, mixed> $replace
     * @param  string|null          $locale
     * @return string
     */
    public function get(string $key, array $replace = [], ?string $locale = null): string;

    /**
     * Get the active locale.
     */
    public function getLocale(): string;

    /**
     * Set the active locale.
     */
    public function setLocale(string $locale): void;

    /**
     * Get the fallback locale.
     */
    public function getFallback(): string;

    /**
     * Set the fallback locale.
     */
    public function setFallback(string $fallback): void;
}
