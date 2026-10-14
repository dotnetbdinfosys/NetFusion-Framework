<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Auth/Tokens/HasApiTokens.php
| Module    : Auth\Tokens
| Namespace : NetFusion\Core\Auth\Tokens
| Purpose   : Trait empowering authenticatable models with API token generation & verification.
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

namespace NetFusion\Core\Auth\Tokens;

use DateTimeInterface;

/**
 * Trait HasApiTokens
 *
 * Attaches Personal Access Token capabilities to any Model (e.g. User).
 *
 * @package NetFusion\Core\Auth\Tokens
 */
trait HasApiTokens
{
    /**
     * Currently authenticated access token for this request.
     */
    protected ?PersonalAccessToken $currentAccessToken = null;

    /**
     * Issue a new personal access token for this entity.
     *
     * @param string                 $name
     * @param array<string>          $abilities
     * @param DateTimeInterface|null $expiresAt
     * @return NewAccessToken
     */
    public function createToken(
        string $name,
        array $abilities = ['*'],
        ?DateTimeInterface $expiresAt = null
    ): NewAccessToken {
        $manager = function_exists('app') && app()->has('token.manager')
            ? app('token.manager')
            : new TokenManager();

        return $manager->createToken(
            tokenableType: static::class,
            tokenableId  : $this->getKey(),
            name         : $name,
            abilities    : $abilities,
            expiresAt    : $expiresAt
        );
    }

    /**
     * Get all personal access tokens for this entity.
     *
     * @return array<PersonalAccessToken>
     */
    public function tokens(): array
    {
        $manager = function_exists('app') && app()->has('token.manager')
            ? app('token.manager')
            : new TokenManager();

        return $manager->getTokensFor(static::class, $this->getKey());
    }

    /**
     * Get the current access token.
     */
    public function currentAccessToken(): ?PersonalAccessToken
    {
        return $this->currentAccessToken;
    }

    /**
     * Set the current access token.
     *
     * @param PersonalAccessToken|null $token
     * @return $this
     */
    public function withAccessToken(?PersonalAccessToken $token): static
    {
        $this->currentAccessToken = $token;
        return $this;
    }

    /**
     * Determine if current access token can perform an action.
     */
    public function tokenCan(string $ability): bool
    {
        return $this->currentAccessToken ? $this->currentAccessToken->can($ability) : false;
    }

    /**
     * Determine if current access token cannot perform an action.
     */
    public function tokenCant(string $ability): bool
    {
        return ! $this->tokenCan($ability);
    }
}
