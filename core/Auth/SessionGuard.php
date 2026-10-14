<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Auth/SessionGuard.php
| Module    : Core\Auth
| Namespace : NetFusion\Core\Auth
| Purpose   : Web session-based stateful authentication guard.
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

use NetFusion\Core\Session\Session;

/**
 * NetFusion Session-Based Authentication Guard
 *
 * Manages user sessions, state persistence, and login/logout state.
 *
 * @package NetFusion\Core\Auth
 * @version 1.0.0
 */
class SessionGuard implements GuardInterface
{
    /**
     * Session storage key for the authenticated user ID.
     */
    protected const SESSION_ID_KEY = '_netfusion_auth_user_id';

    /**
     * Currently authenticated user instance cache.
     */
    protected ?AuthenticatableInterface $user = null;

    /**
     * User provider callback for resolving user by ID.
     *
     * @var callable|null
     */
    protected $userResolver = null;

    /**
     * @param Session       $session
     * @param callable|null $userResolver Function(mixed $id): ?AuthenticatableInterface
     */
    public function __construct(
        protected readonly Session $session,
        ?callable                   $userResolver = null
    ) {
        $this->userResolver = $userResolver;
    }

    /**
     * Set the callback used to reload user from database by ID.
     */
    public function setUserResolver(callable $resolver): static
    {
        $this->userResolver = $resolver;
        return $this;
    }

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
        if ($this->user !== null) {
            return $this->user;
        }

        $id = $this->session->get(self::SESSION_ID_KEY);
        if ($id !== null && $this->userResolver !== null) {
            $this->user = ($this->userResolver)($id);
        }

        return $this->user;
    }

    /**
     * {@inheritDoc}
     */
    public function id(): mixed
    {
        return $this->user()?->getAuthIdentifier() ?? $this->session->get(self::SESSION_ID_KEY);
    }

    /**
     * Log a user into the application.
     */
    public function login(AuthenticatableInterface $user): void
    {
        $this->user = $user;
        $this->session->set(self::SESSION_ID_KEY, $user->getAuthIdentifier());
        $this->session->regenerate();
    }

    /**
     * Log the current user out of the application.
     */
    public function logout(): void
    {
        $this->user = null;
        $this->session->remove(self::SESSION_ID_KEY);
        $this->session->regenerate();
    }

    /**
     * {@inheritDoc}
     */
    public function validate(array $credentials): bool
    {
        // Custom credentials validation logic or delegate to user resolver
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function setUser(AuthenticatableInterface $user): static
    {
        $this->user = $user;
        return $this;
    }
}
