<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Translation/FileLoader.php
| Module    : Core\Translation
| Namespace : NetFusion\Core\Translation
| Purpose   : Translation file loader reading PHP arrays and JSON localization files.
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

class FileLoader
{
    protected string $path;

    /**
     * @var array<string, array<string, array<string, string>>> [locale => [group => [key => val]]]
     */
    protected array $loadedGroups = [];

    /**
     * @var array<string, array<string, string>> [locale => [key => val]]
     */
    protected array $loadedJson = [];

    public function __construct(string $path)
    {
        $this->path = rtrim($path, DIRECTORY_SEPARATOR);
    }

    /**
     * Load group translations for a given locale (alias).
     *
     * @return array<string, string>
     */
    public function load(string $locale, string $group): array
    {
        return $this->loadGroup($locale, $group);
    }

    /**
     * Load group translations for a given locale.
     *
     * @return array<string, string>
     */
    public function loadGroup(string $locale, string $group): array
    {
        if (isset($this->loadedGroups[$locale][$group])) {
            return $this->loadedGroups[$locale][$group];
        }

        $file = $this->path . DIRECTORY_SEPARATOR . $locale . DIRECTORY_SEPARATOR . "{$group}.php";
        if (file_exists($file)) {
            $data = require $file;
            $this->loadedGroups[$locale][$group] = is_array($data) ? $data : [];
        } else {
            $this->loadedGroups[$locale][$group] = [];
        }

        return $this->loadedGroups[$locale][$group];
    }

    /**
     * Load JSON translations for a given locale.
     *
     * @return array<string, string>
     */
    public function loadJson(string $locale): array
    {
        if (isset($this->loadedJson[$locale])) {
            return $this->loadedJson[$locale];
        }

        $file = $this->path . DIRECTORY_SEPARATOR . "{$locale}.json";
        if (file_exists($file)) {
            $decoded = json_decode((string) file_get_contents($file), true);
            $this->loadedJson[$locale] = is_array($decoded) ? $decoded : [];
        } else {
            $this->loadedJson[$locale] = [];
        }

        return $this->loadedJson[$locale];
    }
}
