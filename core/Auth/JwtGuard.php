<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Auth/JwtGuard.php
| Module    : Core\Auth
| Namespace : NetFusion\Core\Auth
| Purpose   : JWT Bearer Token Authentication Guard for stateless API requests.
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

namespace NetFusion\Core\Auth;

use NetFusion\Core\Http\Request;

/**
 * NetFusion JWT Guard
 *
 * Resolves a user from an HTTP Authorization: Bearer <token> header
 * using the JwtTokenManager. Designed for stateless API authentication.
 *
 * Usage in routes:
 *   $router->group(['middleware' => 'auth:jwt'], function ($r) { ... });
 *
 * @package NetFusion\Core\Auth
 * @version 1.0.0
 */
class JwtGuard implements GuardInterface
{
    /**
     * Cached resolved user.
     */
    protected ?AuthenticatableInterface $user = null;

    /**
     * Cached parsed JWT payload.
     *
     * @var array<string,mixed>|null
     */
    protected ?array $payload = null;

    /**
     * Whether the user has been resolved (to avoid re-parsing).
     */
    protected bool $resolved = false;

    /**
     * @param JwtTokenManager $tokenManager JWT manager for parsing tokens.
     * @param Request         $request      Current HTTP request.
     */
    public function __construct(
        protected readonly JwtTokenManager $tokenManager,
        protected readonly Request         $request,
    ) {}

    // =========================================================================
    //  GuardInterface Implementation
    // =========================================================================

    /**
     * {@inheritDoc}
     */
    public function check(): bool
    {
        return $this->user() !== null;
    }

    /**
     * {@inheritDoc}
     */
    public function guest(): bool
    {
        return !$this->check();
    }

    /**
     * {@inheritDoc}
     */
    public function user(): ?AuthenticatableInterface
    {
        if ($this->resolved) {
            return $this->user;
        }

        $this->resolved = true;
        $token = $this->extractBearerToken();

        if ($token === null) {
            return null;
        }

        $payload = $this->tokenManager->validate($token);

        if ($payload === null) {
            return null;
        }

        $this->payload = $payload;

        // Build a lightweight in-memory user from JWT claims
        $this->user = new JwtUser($payload);

        return $this->user;
    }

    /**
     * {@inheritDoc}
     */
    public function id(): int|string|null
    {
        return $this->user()?->getAuthIdentifier();
    }

    /**
     * {@inheritDoc}
     */
    public function validate(array $credentials): bool
    {
        // JWT guard is stateless — validate via token, not credentials.
        // Use JwtTokenManager::validate() for token-based validation.
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function setUser(AuthenticatableInterface $user): static
    {
        $this->user     = $user;
        $this->resolved = true;
        return $this;
    }

    /**
     * Logout: revoke the current token.
     */
    public function logout(): void
    {
        $token = $this->extractBearerToken();

        if ($token !== null) {
            $this->tokenManager->revokeToken($token);
        }

        $this->user     = null;
        $this->payload  = null;
        $this->resolved = false;
    }

    /**
     * Check if user has a specific role.
     */
    public function hasRole(string $role): bool
    {
        $user = $this->user();
        return $user instanceof JwtUser && in_array($role, $user->getRoles(), true);
    }

    /**
     * Check if user has a specific permission.
     */
    public function can(string $permission): bool
    {
        $user = $this->user();
        return $user instanceof JwtUser && in_array($permission, $user->getPermissions(), true);
    }


    // =========================================================================
    //  JWT-Specific Helpers
    // =========================================================================

    /**
     * Get the raw JWT payload (if resolved).
     *
     * @return array<string,mixed>|null
     */
    public function payload(): ?array
    {
        $this->user(); // Trigger resolution
        return $this->payload;
    }

    /**
     * Extract the Bearer token from the Authorization header.
     *
     * @return string|null
     */
    protected function extractBearerToken(): ?string
    {
        $header = $this->request->header('Authorization', '');

        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        // Also check query param for WebSocket / Swagger UI compatibility
        $token = $this->request->query('token');
        return is_string($token) && !empty($token) ? $token : null;
    }
}
