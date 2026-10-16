<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Http/Helpers/SiteSettingsHelper.php
| Module    : Helpers
| Namespace : NetFusion\App\Http\Helpers
| Purpose   : Persistent Site Settings, Branding, Logo, Favicon, Header & Footer Customizer.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| License   : MIT
| Version   : 1.3.5
|
|--------------------------------------------------------------------------
*/

namespace NetFusion\App\Http\Helpers;

class SiteSettingsHelper
{
    protected static ?array $cachedSettings = null;

    /**
     * Default branding & site settings.
     */
    protected static array $defaults = [
        'site_name'           => 'NetFusion Framework',
        'site_tagline'        => 'Enterprise Full-Stack & HMVC Platform',
        'logo_icon'           => '⚡',
        'logo_url'            => '',
        'favicon_url'         => '',
        'header_title'        => 'Executive Dashboard',
        'header_announcement' => 'Welcome to NetFusion Enterprise Hub',
        'footer_copyright'    => 'NetFusion Framework &bull; Developed by Md. Atiquzzaman Ashish &bull; &copy; 2026 DotNetBD',
        'footer_company'      => 'DotNetBD',
        'footer_website'      => 'https://www.dotnetbd.com',
        'default_language'    => 'en',
    ];

    /**
     * Get settings file path.
     */
    public static function getStoragePath(): string
    {
        return storage_path('framework/site_settings.json');
    }

    /**
     * Retrieve all current site settings.
     */
    public static function get(): array
    {
        if (self::$cachedSettings !== null) {
            return self::$cachedSettings;
        }

        $path = self::getStoragePath();
        if (is_file($path)) {
            $data = json_decode((string) file_get_contents($path), true);
            if (is_array($data)) {
                self::$cachedSettings = array_merge(self::$defaults, $data);
                return self::$cachedSettings;
            }
        }

        self::$cachedSettings = self::$defaults;
        return self::$cachedSettings;
    }

    /**
     * Update and save site settings to persistent storage.
     */
    public static function save(array $newSettings): array
    {
        $current = self::get();
        $merged  = array_merge($current, $newSettings);

        $path = self::getStoragePath();
        $dir  = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        file_put_contents(
            $path,
            json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        self::$cachedSettings = $merged;
        return $merged;
    }

    /**
     * Resolve active language from request (query, cookie, or default 'en').
     */
    public static function resolveLocale(): string
    {
        if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'bn'], true)) {
            $locale = $_GET['lang'];
            if (!headers_sent()) {
                setcookie('app_locale', $locale, time() + (86400 * 365), '/');
            }
            return $locale;
        }

        if (isset($_COOKIE['app_locale']) && in_array($_COOKIE['app_locale'], ['en', 'bn'], true)) {
            return $_COOKIE['app_locale'];
        }

        return self::get()['default_language'] ?? 'en';
    }
}
