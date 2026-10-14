<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Translation/Translator.php
| Module    : Core\Translation
| Namespace : NetFusion\Core\Translation
| Purpose   : Pure PHP enterprise internationalization (i18n) and localization translator.
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

namespace NetFusion\Core\Translation;

use NetFusion\Core\Translation\Contracts\TranslatorInterface;

class Translator implements TranslatorInterface
{
    protected FileLoader $loader;
    protected string $locale;
    protected string $fallback;

    public function __construct(FileLoader $loader, string $locale = 'en', string $fallback = 'en')
    {
        $this->loader   = $loader;
        $this->locale   = $locale;
        $this->fallback = $fallback;
    }

    /**
     * Get translation message by key.
     */
    public function get(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale = $locale ?? $this->locale;

        $line = $this->retrieveLine($key, $locale);

        // Try fallback locale if missing
        if ($line === null && $locale !== $this->fallback) {
            $line = $this->retrieveLine($key, $this->fallback);
        }

        // Return key if no translation found
        if ($line === null) {
            return $this->interpolate($key, $replace);
        }

        return $this->interpolate($line, $replace);
    }

    /**
     * Determine if a translation exists for a given key.
     */
    public function has(string $key, ?string $locale = null): bool
    {
        $locale = $locale ?? $this->locale;
        return $this->retrieveLine($key, $locale) !== null;
    }

    /**
     * Select pluralization string based on count.
     */
    public function choice(string $key, int $number, array $replace = [], ?string $locale = null): string
    {
        $line = $this->get($key, $replace, $locale);
        $replace['count'] = $number;

        $segments = explode('|', $line);

        foreach ($segments as $segment) {
            $segment = trim($segment);

            // Exact match: {0} or {1} or {5}
            if (preg_match('/^\{(\d+)\}\s*(.*)$/u', $segment, $m)) {
                if ((int) $m[1] === $number) {
                    return $this->interpolate($m[2], $replace);
                }
                continue;
            }

            // Range match: [1,5] or [2,*] or [*,10]
            if (preg_match('/^\[([0-9*]+)\s*,\s*([0-9*]+)\]\s*(.*)$/u', $segment, $m)) {
                $from = $m[1] === '*' ? -PHP_INT_MAX : (int) $m[1];
                $to   = $m[2] === '*' ? PHP_INT_MAX : (int) $m[2];

                if ($number >= $from && $number <= $to) {
                    return $this->interpolate($m[3], $replace);
                }
                continue;
            }
        }

        // Standard 2-segment fallback (singular|plural)
        if (count($segments) >= 2) {
            $chosen = ($number === 1) ? $segments[0] : $segments[1];
            return $this->interpolate(trim($chosen), $replace);
        }

        return $this->interpolate(trim($segments[0]), $replace);
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    public function getFallback(): string
    {
        return $this->fallback;
    }

    public function setFallback(string $fallback): void
    {
        $this->fallback = $fallback;
    }

    /**
     * Lookup string from PHP group or JSON dictionary.
     */
    protected function retrieveLine(string $key, string $locale): ?string
    {
        // 1. Group lookup (e.g. 'messages.welcome')
        if (str_contains($key, '.')) {
            $parts = explode('.', $key, 2);
            $group = $parts[0];
            $item  = $parts[1];

            $groupData = $this->loader->loadGroup($locale, $group);
            if (isset($groupData[$item])) {
                return (string) $groupData[$item];
            }
        }

        // 2. JSON string lookup (e.g. 'Welcome to NetFusion')
        $jsonData = $this->loader->loadJson($locale);
        if (isset($jsonData[$key])) {
            return (string) $jsonData[$key];
        }

        return null;
    }

    /**
     * Interpolate placeholder replacements (:name or {name}).
     *
     * @param  string               $message
     * @param  array<string, mixed> $replace
     * @return string
     */
    protected function interpolate(string $message, array $replace): string
    {
        if (empty($replace)) {
            return $message;
        }

        foreach ($replace as $key => $value) {
            $value = (string) $value;
            $message = str_replace([":{$key}", "{{$key}}"], $value, $message);
        }

        return $message;
    }
}
