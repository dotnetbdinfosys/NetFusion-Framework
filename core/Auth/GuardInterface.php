<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Auth/GuardInterface.php
| Module    : Core\Auth
| Namespace : NetFusion\Core\Auth
| Purpose   : Contract for authentication guards (session-based, bearer token, etc.).
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
 * NetFusion Authentication Guard Contract
 *
 * Provides a unified interface for inspecting authentication status,
 * retrieving the authenticated user, and validating credentials across guards.
 *
 * @package NetFusion\Core\Auth
 * @version 1.0.0
 */
interface GuardInterface
{
    /**
     * Determine if the current user is authenticated.
     */
    public function check(): bool;

    /**
     * Determine if the current user is a guest (not authenticated).
     */
    public function guest(): bool;

    /**
     * Get the currently authenticated user instance.
     */
    public function user(): ?AuthenticatableInterface;

    /**
     * Get the ID for the currently authenticated user.
     */
    public function id(): mixed;

    /**
     * Validate a user's credentials without logging in.
     *
     * @param array<string, mixed> $credentials
     */
    public function validate(array $credentials): bool;

    /**
     * Set the current user.
     */
    public function setUser(AuthenticatableInterface $user): static;
}
