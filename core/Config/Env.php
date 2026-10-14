<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Config/Env.php
| Module    : Core\Config
| Namespace : NetFusion\Core\Config
| Purpose   : Lightweight, type-safe .env parser with boolean, null, numeric casting and system env precedence.
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

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Config/Env.php
| Module    : Core\Config
| Namespace : NetFusion\Core\Config
| Purpose   : Lightweight, type-safe .env parser with boolean, null, numeric casting and system env precedence.
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

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Config/Env.php
| Module    : Core\Config
| Namespace : NetFusion\Core\Config
| Purpose   : Lightweight, type-safe .env parser with boolean, null, numeric casting and system env precedence.
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

namespace NetFusion\Core\Config;

/**
 * NetFusion Env - A Secure, Lightweight .env File Parser
 *
 * Parses a .env file and populates $_ENV, $_SERVER, and putenv().
 * Supports type casting (bool, null, integers), quoted strings,
 * comments, and multi-line values. Does NOT use any external
 * dependencies, keeping the framework lightweight.
 *
 * @package NetFusion\Core\Config
 * @version 1.0.0
 */
final class Env
{
    /**
     * The directory path where the .env file is located.
     */
    private readonly string $path;

    /**
     * Whether the .env file has been loaded already.
     */
    private static bool $loaded = false;

    /**
     * Cache of parsed env variables.
     *
     * @var array<string, mixed>
     */
    private static array $cache = [];

    /**
     * @param string $basePath The base directory of the application.
     */
    public function __construct(string $basePath)
    {
        $this->path = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.env';
    }

    /**
     * Load the .env file if it hasn't been loaded yet.
     *
     * Silently skips if the .env file does not exist (e.g. in
     * production environments using system-level env vars).
     *
     * @throws \RuntimeException If the .env file cannot be read.
     */
    public function load(): void
    {
        if (static::$loaded) {
            return;
        }

        if (!is_file($this->path)) {
            // No .env file found - relying on system environment variables.
            static::$loaded = true;
            return;
        }

        if (!is_readable($this->path)) {
            throw new \RuntimeException(
                sprintf('The .env file at [%s] is not readable. Check file permissions.', $this->path)
            );
        }

        $this->parse(file_get_contents($this->path));
        static::$loaded = true;
    }

    /**
     * Forcefully reload the .env file (useful in testing).
     */
    public function reload(): void
    {
        static::$loaded = false;
        static::$cache = [];
        $this->load();
    }

    /**
     * Parse the raw content of the .env file line by line.
     *
     * @param string $content The raw string content of the .env file.
     */
    private function parse(string $content): void
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $content));

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip blank lines and full-line comments
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Skip lines that don't contain an '=' sign
            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = $this->splitKeyValue($line);

            if (empty($key)) {
                continue;
            }

            $key   = $this->sanitizeKey($key);
            $value = $this->castValue($this->stripInlineComment($value));

            $this->set($key, $value);
        }
    }

    /**
     * Split a raw env line into key and value parts.
     * Handles '=' signs within quoted values.
     *
     * @param  string $line A raw line like: KEY="value=with=equals"
     * @return array{string, string}
     */
    private function splitKeyValue(string $line): array
    {
        $pos = strpos($line, '=');

        if ($pos === false) {
            return [$line, ''];
        }

        return [
            substr($line, 0, $pos),
            substr($line, $pos + 1),
        ];
    }

    /**
     * Sanitize the environment variable key.
     * Removes 'export ' prefix if present and trims whitespace.
     *
     * @param  string $key Raw key string.
     * @return string Sanitized key.
     */
    private function sanitizeKey(string $key): string
    {
        $key = trim($key);

        // Handle 'export KEY=VALUE' syntax
        if (str_starts_with(strtolower($key), 'export ')) {
            $key = ltrim(substr($key, 7));
        }

        return $key;
    }

    /**
     * Strip an inline comment from a raw value string.
     * Only strips comments that appear AFTER a space (so URLs are safe).
     *
     * @param  string $value Raw value string.
     * @return string Value with inline comment stripped.
     */
    private function stripInlineComment(string $value): string
    {
        $value = trim($value);

        // Quoted values: do not strip comments inside quotes
        if (str_starts_with($value, '"') || str_starts_with($value, "'")) {
            return $value;
        }

        // Strip trailing inline comments: VALUE # this is a comment
        if (($pos = strpos($value, ' #')) !== false) {
            $value = rtrim(substr($value, 0, $pos));
        }

        return $value;
    }

    /**
     * Cast the raw value string to the appropriate PHP type.
     *
     * Casting rules:
     *   - "true" / "yes" / "on"  => true (bool)
     *   - "false" / "no" / "off" => false (bool)
     *   - "null" / "~"           => null
     *   - "(null)"               => null
     *   - "123"                  => 123 (int)
     *   - "12.5"                 => 12.5 (float)
     *   - Quoted strings         => string without quotes
     *
     * @param  string $value Raw value string.
     * @return bool|float|int|string|null Typed PHP value.
     */
    private function castValue(string $value): bool|float|int|string|null
    {
        $value = trim($value);

        // Boolean TRUE variants
        if (in_array(strtolower($value), ['true', '(true)', 'yes', 'on'], true)) {
            return true;
        }

        // Boolean FALSE variants
        if (in_array(strtolower($value), ['false', '(false)', 'no', 'off'], true)) {
            return false;
        }

        // Null variants
        if (in_array(strtolower($value), ['null', '(null)', '~', ''], true)) {
            return null;
        }

        // Double-quoted strings: "some value"
        if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
            return str_replace(
                ['\n', '\r', '\t', '\\\\', '\\"'],
                ["\n", "\r", "\t", '\\', '"'],
                substr($value, 1, -1)
            );
        }

        // Single-quoted strings: 'some value'
        if (str_starts_with($value, "'") && str_ends_with($value, "'")) {
            return substr($value, 1, -1);
        }

        // Numeric values
        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        return $value;
    }

    /**
     * Set an environment variable across all three PHP env mechanisms.
     *
     * @param string                      $key   The env variable name.
     * @param bool|float|int|string|null  $value The typed value.
     */
    private function set(string $key, bool|float|int|string|null $value): void
    {
        // Serialize the value for putenv() since it only accepts strings
        $putenvValue = match (true) {
            $value === true  => 'true',
            $value === false => 'false',
            $value === null  => '',
            default          => (string) $value,
        };

        // Don't overwrite values already set in the actual system environment
        // This allows server/container env vars to take precedence over .env
        if (getenv($key) === false) {
            putenv("{$key}={$putenvValue}");
            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;
        }

        // Always populate the in-memory cache
        static::$cache[$key] = $value;
    }

    /**
     * Retrieve an environment variable by name.
     * Checks the in-memory cache first, then PHP's env mechanisms.
     *
     * @param  string                     $key     The env variable name.
     * @param  bool|float|int|string|null $default Default value if not found.
     * @return bool|float|int|string|null
     */
    public static function get(
        string $key,
        bool|float|int|string|null $default = null
    ): bool|float|int|string|null {
        // Check our typed in-memory cache first
        if (array_key_exists($key, static::$cache)) {
            return static::$cache[$key];
        }

        // Fall back to PHP's env mechanisms (for system-set vars)
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false) {
            return $default;
        }

        return $value;
    }

    /**
     * Check whether an environment variable exists.
     *
     * @param  string $key The env variable name.
     * @return bool
     */
    public static function has(string $key): bool
    {
        return array_key_exists($key, static::$cache)
            || isset($_ENV[$key])
            || isset($_SERVER[$key])
            || getenv($key) !== false;
    }

    /**
     * Get all parsed environment variables (from the in-memory cache).
     *
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return static::$cache;
    }
}
