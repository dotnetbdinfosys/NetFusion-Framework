<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Auth/AuthenticatableInterface.php
| Module    : Core\Auth
| Namespace : NetFusion\Core\Auth
| Purpose   : Contract for authenticatable user models supporting multi-guard RBAC authorization.
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
 * NetFusion Authenticatable User Contract
 *
 * Defines authentication identifiers and role-based access control (RBAC) methods.
 *
 * @package NetFusion\Core\Auth
 * @version 1.0.0
 */
interface AuthenticatableInterface
{
    /**
     * Get the unique identifier for the user (usually primary key id or UUID).
     */
    public function getAuthIdentifier(): mixed;

    /**
     * Get the password hash for the user.
     */
    public function getAuthPassword(): string;

    /**
     * Check if user possesses the specified role(s).
     *
     * @param string|string[] $roles
     */
    public function hasRole(string|array $roles): bool;

    /**
     * Check if user possesses the specified permission.
     *
     * @param string $permission
     */
    public function can(string $permission): bool;
}
