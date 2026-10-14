<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Auth/Tokens/PersonalAccessToken.php
| Module    : Auth\Tokens
| Namespace : NetFusion\Core\Auth\Tokens
| Purpose   : Model representing a Personal Access Token (PAT).
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

use NetFusion\Core\Database\Model;

/**
 * Class PersonalAccessToken
 *
 * Represents an issued Personal Access Token record in the database.
 *
 * @package NetFusion\Core\Auth\Tokens
 */
class PersonalAccessToken extends Model
{
    protected string $table = 'personal_access_tokens';

    protected array $fillable = [
        'tokenable_type',
        'tokenable_id',
        'name',
        'token',
        'abilities',
        'last_used_at',
        'expires_at',
        'created_at',
        'updated_at',
    ];

    protected array $hidden = [
        'token',
    ];

    /**
     * Get parsed abilities array.
     *
     * @return array<string>
     */
    public function getAbilities(): array
    {
        $abilities = $this->getAttribute('abilities');

        if (is_array($abilities)) {
            return $abilities;
        }

        if (is_string($abilities) && $abilities !== '') {
            $decoded = json_decode($abilities, true);
            return is_array($decoded) ? $decoded : [];
        }

        return ['*'];
    }

    /**
     * Determine if the token has the given ability.
     *
     * @param string $ability
     * @return bool
     */
    public function can(string $ability): bool
    {
        $abilities = $this->getAbilities();

        if (in_array('*', $abilities, true)) {
            return true;
        }

        if (in_array($ability, $abilities, true)) {
            return true;
        }

        // Support wildcard matching e.g. "users:*" matches "users:read"
        foreach ($abilities as $perm) {
            if (str_ends_with($perm, ':*')) {
                $prefix = substr($perm, 0, -2);
                if (str_starts_with($ability, $prefix . ':')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Determine if the token does not have the given ability.
     *
     * @param string $ability
     * @return bool
     */
    public function cant(string $ability): bool
    {
        return ! $this->can($ability);
    }

    /**
     * Determine if the token has expired.
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        $expiresAt = $this->getAttribute('expires_at');
        if ($expiresAt === null || $expiresAt === '') {
            return false;
        }

        $timestamp = is_numeric($expiresAt) ? (int) $expiresAt : strtotime((string) $expiresAt);
        return $timestamp !== false && $timestamp < time();
    }
}
