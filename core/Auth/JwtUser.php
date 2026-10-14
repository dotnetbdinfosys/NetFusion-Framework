<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Auth/JwtUser.php
| Module    : Core\Auth
| Namespace : NetFusion\Core\Auth
| Purpose   : Lightweight in-memory user object hydrated from JWT payload claims.
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

/**
 * NetFusion JWT User
 *
 * A lightweight, stateless user representation built from JWT payload claims.
 * Implements AuthenticatableInterface so it works transparently with
 * existing RBAC helpers (hasRole, can).
 *
 * @package NetFusion\Core\Auth
 * @version 1.0.0
 */
class JwtUser implements AuthenticatableInterface
{
    /**
     * @param array<string,mixed> $payload The decoded JWT claims.
     */
    public function __construct(
        protected readonly array $payload
    ) {}

    /**
     * {@inheritDoc}
     */
    public function getAuthIdentifier(): int|string
    {
        return $this->payload['sub'] ?? 0;
    }

    /**
     * {@inheritDoc}
     */
    public function getAuthPassword(): string
    {
        // Stateless — no password stored in JWT.
        return '';
    }

    /**
     * Get user roles from JWT payload.
     *
     * @return array<int,string>
     */
    public function getRoles(): array
    {
        return (array) ($this->payload['roles'] ?? []);
    }

    /**
     * Get user permissions from JWT payload.
     *
     * @return array<int,string>
     */
    public function getPermissions(): array
    {
        return (array) ($this->payload['perms'] ?? []);
    }

    /**
     * {@inheritDoc}
     */
    public function hasRole(string|array $roles): bool
    {
        $userRoles = $this->getRoles();
        foreach ((array) $roles as $role) {
            if (in_array($role, $userRoles, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function can(string $permission): bool
    {
        return in_array($permission, $this->getPermissions(), true);
    }

    /**
     * Get a specific claim value from the JWT payload.
     *
     * @param  string $claim   The claim name.
     * @param  mixed  $default Default if claim not present.
     * @return mixed
     */
    public function claim(string $claim, mixed $default = null): mixed
    {
        return $this->payload[$claim] ?? $default;
    }

    /**
     * Get the full JWT payload.
     *
     * @return array<string,mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }
}
