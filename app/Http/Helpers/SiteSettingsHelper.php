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
|             Directly powered by MySQL Database table `site_settings`.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| License   : MIT
| Version   : 1.5.3
|
|--------------------------------------------------------------------------
*/

namespace NetFusion\App\Http\Helpers;

use NetFusion\App\Services\ModuleDataRepository;
use PDO;
use Throwable;

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
     * Retrieve all current site settings from MySQL database.
     */
    public static function get(): array
    {
        if (self::$cachedSettings !== null) {
            return self::$cachedSettings;
        }

        try {
            $pdo = ModuleDataRepository::getPdo();
            ModuleDataRepository::ensureTable('site_settings');

            $stmt = $pdo->query("SELECT `key_name`, `value_data` FROM `site_settings`");
            $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            if (!empty($rows)) {
                $decoded = [];
                foreach ($rows as $k => $v) {
                    $json = json_decode((string)$v, true);
                    $decoded[$k] = $json !== null ? $json : $v;
                }
                self::$cachedSettings = array_merge(self::$defaults, $decoded);
                return self::$cachedSettings;
            }
        } catch (Throwable $e) {
            // fallback to defaults if db connection issue
        }

        self::$cachedSettings = self::$defaults;
        return self::$cachedSettings;
    }

    /**
     * Update and save site settings to MySQL database.
     */
    public static function save(array $newSettings): array
    {
        $current = self::get();
        $merged  = array_merge($current, $newSettings);

        try {
            $pdo = ModuleDataRepository::getPdo();
            ModuleDataRepository::ensureTable('site_settings');

            $stmt = $pdo->prepare("INSERT INTO `site_settings` (`key_name`, `value_data`, `updated_at`) 
                VALUES (?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE `value_data` = VALUES(`value_data`), `updated_at` = NOW()");

            foreach ($merged as $k => $v) {
                $stmt->execute([$k, is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string)$v]);
            }
        } catch (Throwable $e) {
            error_log("Failed to save site_settings into MySQL: " . $e->getMessage());
        }

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
