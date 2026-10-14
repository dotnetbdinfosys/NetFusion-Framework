<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Security/Sanitizer.php
| Module    : Core\Security
| Namespace : NetFusion\Core\Security
| Purpose   : Recursive input sanitizer removing null bytes, trimming strings, and escaping HTML entities.
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
| File      : core/Security/Sanitizer.php
| Module    : Core\Security
| Namespace : NetFusion\Core\Security
| Purpose   : Recursive input sanitizer removing null bytes, trimming strings, and escaping HTML entities.
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
| File      : core/Security/Sanitizer.php
| Module    : Core\Security
| Namespace : NetFusion\Core\Security
| Purpose   : Recursive input sanitizer removing null bytes, trimming strings, and escaping HTML entities.
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

namespace NetFusion\Core\Security;

/**
 * NetFusion Input Sanitizer & XSS Prevention
 *
 * Provides recursive sanitization of user-provided input, string trimming,
 * null-byte removal, and HTML escaping.
 *
 * @package NetFusion\Core\Security
 * @version 1.0.0
 */
class Sanitizer
{
    /**
     * Sanitize any value (scalar, array, or null).
     */
    public static function clean(mixed $value): mixed
    {
        if (is_array($value)) {
            return self::cleanArray($value);
        }

        if (is_string($value)) {
            return self::cleanString($value);
        }

        return $value;
    }

    /**
     * Sanitize a single string (strips null-bytes and trims invisible whitespace).
     */
    public static function cleanString(string $value): string
    {
        // Strip null bytes
        $value = str_replace("\0", '', $value);
        return trim($value);
    }

    /**
     * Recursively sanitize all keys and values in an array.
     *
     * @param  array<string|int, mixed> $data
     * @return array<string|int, mixed>
     */
    public static function cleanArray(array $data): array
    {
        $sanitized = [];
        foreach ($data as $key => $value) {
            $cleanedKey = is_string($key) ? self::cleanString($key) : $key;
            $sanitized[$cleanedKey] = self::clean($value);
        }
        return $sanitized;
    }

    /**
     * Escape a string for safe HTML output.
     */
    public static function escape(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
