<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Security/Csrf.php
| Module    : Core\Security
| Namespace : NetFusion\Core\Security
| Purpose   : Cryptographically secure CSRF token generator and timing-safe hash_equals validator.
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
| File      : core/Security/Csrf.php
| Module    : Core\Security
| Namespace : NetFusion\Core\Security
| Purpose   : Cryptographically secure CSRF token generator and timing-safe hash_equals validator.
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
| File      : core/Security/Csrf.php
| Module    : Core\Security
| Namespace : NetFusion\Core\Security
| Purpose   : Cryptographically secure CSRF token generator and timing-safe hash_equals validator.
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
 * NetFusion CSRF Protection Service
 *
 * Cryptographically secure token generator and validator using random_bytes()
 * and timing-attack-safe hash_equals().
 *
 * @package NetFusion\Core\Security
 * @version 1.0.0
 */
class Csrf
{
    /**
     * Session key name for the CSRF token.
     */
    private const SESSION_KEY = '_netfusion_csrf_token';

    /**
     * Cached token in memory.
     */
    private ?string $token = null;

    /**
     * Generate or retrieve the active CSRF token.
     */
    public function token(): string
    {
        if ($this->token !== null) {
            return $this->token;
        }

        $this->startSessionIfNeeded();

        if (isset($_SESSION[self::SESSION_KEY]) && is_string($_SESSION[self::SESSION_KEY]) && $_SESSION[self::SESSION_KEY] !== '') {
            $this->token = $_SESSION[self::SESSION_KEY];
            return $this->token;
        }

        $this->token = bin2hex(random_bytes(32));
        $_SESSION[self::SESSION_KEY] = $this->token;

        return $this->token;
    }

    /**
     * Validate an incoming token against the stored session token.
     *
     * @param  string|null $token
     * @return bool
     */
    public function validate(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        $stored = $this->token();
        return hash_equals($stored, $token);
    }

    /**
     * Regenerate the CSRF token (recommended on login / privilege change).
     */
    public function regenerate(): string
    {
        $this->startSessionIfNeeded();
        $this->token = bin2hex(random_bytes(32));
        $_SESSION[self::SESSION_KEY] = $this->token;
        return $this->token;
    }

    /**
     * Generate an HTML hidden input field containing the token.
     */
    public function field(): string
    {
        return sprintf(
            '<input type="hidden" name="_token" value="%s">',
            htmlspecialchars($this->token(), ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Ensure session is started if not already active.
     */
    private function startSessionIfNeeded(): void
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
    }
}
