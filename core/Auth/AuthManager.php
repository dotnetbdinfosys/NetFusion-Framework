<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Auth/AuthManager.php
| Module    : Core\Auth
| Namespace : NetFusion\Core\Auth
| Purpose   : Central multi-guard authentication manager resolving web and api guards.
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

use NetFusion\Core\Application;
use NetFusion\Core\Http\Request;
use NetFusion\Core\Session\Session;
use RuntimeException;

/**
 * NetFusion Authentication Manager
 *
 * Coordinates authentication guards ('web', 'api') and provides direct access
 * to authentication state.
 *
 * @package NetFusion\Core\Auth
 * @version 1.0.0
 */
class AuthManager
{
    /**
     * Active guard instances.
     *
     * @var array<string, GuardInterface>
     */
    protected array $guards = [];

    /**
     * Custom guard creator callables.
     *
     * @var array<string, callable>
     */
    protected array $customCreators = [];

    /**
     * Default guard name.
     */
    protected string $defaultGuard = 'web';

    /**
     * @param Application $app
     */
    public function __construct(
        protected readonly Application $app
    ) {}

    /**
     * Get a guard instance by name.
     *
     * @param  string|null $name 'web' or 'api'
     * @return GuardInterface
     */
    public function guard(?string $name = null): GuardInterface
    {
        $name = $name ?: $this->defaultGuard;

        if (!isset($this->guards[$name])) {
            $this->guards[$name] = $this->resolveGuard($name);
        }

        return $this->guards[$name];
    }

    /**
     * Set default guard name.
     */
    public function shouldUse(string $name): void
    {
        $this->defaultGuard = $name;
    }

    /**
     * Register a custom guard creator.
     */
    public function extend(string $name, callable $creator): static
    {
        $this->customCreators[$name] = $creator;
        return $this;
    }

    /**
     * Resolve guard instance.
     */
    protected function resolveGuard(string $name): GuardInterface
    {
        if (isset($this->customCreators[$name])) {
            return ($this->customCreators[$name])($this->app);
        }

        return match ($name) {
            'web'  => new SessionGuard($this->app->make(Session::class)),
            'api'  => new TokenGuard(Request::capture()),
            'jwt'  => new JwtGuard(
                $this->app->make(JwtTokenManager::class),
                Request::capture()
            ),
            default => throw new RuntimeException("Authentication guard [{$name}] is not defined."),
        };
    }

    /**
     * Forward calls to default guard.
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->guard()->$method(...$parameters);
    }
}
