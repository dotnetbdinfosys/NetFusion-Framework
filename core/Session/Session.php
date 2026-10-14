<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Session/Session.php
| Module    : Core\Session
| Namespace : NetFusion\Core\Session
| Purpose   : Type-safe session management service with flash message and lifecycle capabilities.
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

namespace NetFusion\Core\Session;

/**
 * NetFusion Session Manager
 *
 * Provides object-oriented access to session storage, flash messages,
 * and session fixation protection.
 *
 * @package NetFusion\Core\Session
 * @version 1.0.0
 */
class Session
{
    /**
     * Session flash key namespace.
     */
    protected const FLASH_KEY = '_netfusion_flash';

    /**
     * Constructor starts session if not already started.
     */
    public function __construct()
    {
        $this->start();
    }

    /**
     * Start session safely if not active.
     */
    public function start(): bool
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            return @session_start();
        }

        return session_status() === PHP_SESSION_ACTIVE;
    }

    /**
     * Get a session value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Set a session value.
     */
    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Check if a session key exists.
     */
    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    /**
     * Remove a session key.
     */
    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Clear all session data.
     */
    public function clear(): void
    {
        $_SESSION = [];
    }

    /**
     * Get the session ID.
     */
    public function id(): string
    {
        return session_id() ?: '';
    }

    /**
     * Regenerate the session ID (protect against session fixation).
     */
    public function regenerate(bool $deleteOldSession = true): bool
    {
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            return @session_regenerate_id($deleteOldSession);
        }
        return false;
    }

    // =========================================================================
    //  Flash Messaging (One-Time Storage)
    // =========================================================================

    /**
     * Store a flash message for the next request.
     */
    public function flash(string $key, mixed $value): void
    {
        $_SESSION[self::FLASH_KEY][$key] = $value;
    }

    /**
     * Retrieve and clear a flash message.
     */
    public function getFlash(string $key, mixed $default = null): mixed
    {
        if (!isset($_SESSION[self::FLASH_KEY][$key])) {
            return $default;
        }

        $value = $_SESSION[self::FLASH_KEY][$key];
        unset($_SESSION[self::FLASH_KEY][$key]);

        return $value;
    }

    /**
     * Check if a flash message exists.
     */
    public function hasFlash(string $key): bool
    {
        return isset($_SESSION[self::FLASH_KEY][$key]);
    }
}
