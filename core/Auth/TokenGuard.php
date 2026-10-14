<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Auth/TokenGuard.php
| Module    : Core\Auth
| Namespace : NetFusion\Core\Auth
| Purpose   : Stateless Bearer token authentication guard for API requests.
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
 * NetFusion Token / Bearer Authentication Guard
 *
 * Inspects Authorization: Bearer tokens on incoming HTTP requests and resolves the user.
 *
 * @package NetFusion\Core\Auth
 * @version 1.0.0
 */
class TokenGuard implements GuardInterface
{
    /**
     * Authenticated user cache for current request.
     */
    protected ?AuthenticatableInterface $user = null;

    /**
     * Token resolver callback.
     *
     * @var callable|null
     */
    protected $tokenResolver = null;

    /**
     * @param Request       $request
     * @param callable|null $tokenResolver Function(string $token): ?AuthenticatableInterface
     */
    public function __construct(
        protected readonly Request $request,
        ?callable                  $tokenResolver = null
    ) {
        $this->tokenResolver = $tokenResolver;
    }

    /**
     * Set the callback used to find a user by their bearer token.
     */
    public function setTokenResolver(callable $resolver): static
    {
        $this->tokenResolver = $resolver;
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

        $token = $this->request->bearerToken() ?? $this->request->input('api_token');

        if (is_string($token) && $token !== '' && $this->tokenResolver !== null) {
            $this->user = ($this->tokenResolver)($token);
        }

        return $this->user;
    }

    /**
     * {@inheritDoc}
     */
    public function id(): mixed
    {
        return $this->user()?->getAuthIdentifier();
    }

    /**
     * {@inheritDoc}
     */
    public function validate(array $credentials): bool
    {
        $token = $credentials['token'] ?? null;
        if (!is_string($token) || $this->tokenResolver === null) {
            return false;
        }

        return ($this->tokenResolver)($token) !== null;
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
