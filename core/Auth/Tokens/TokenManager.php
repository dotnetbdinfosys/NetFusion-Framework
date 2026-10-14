<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Auth/Tokens/TokenManager.php
| Module    : Auth\Tokens
| Namespace : NetFusion\Core\Auth\Tokens
| Purpose   : Service managing lifecycle of Personal Access Tokens (PAT).
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
use NetFusion\Core\Database\DatabaseManager;

/**
 * Class TokenManager
 *
 * Handles creation, hashing, resolution, and revocation of Personal Access Tokens.
 *
 * @package NetFusion\Core\Auth\Tokens
 */
class TokenManager
{
    /**
     * @param DatabaseManager|null $db
     */
    public function __construct(protected ?DatabaseManager $db = null)
    {
    }

    /**
     * Create a new Personal Access Token for a tokenable entity.
     *
     * @param string                 $tokenableType Model class or alias.
     * @param int|string             $tokenableId   Entity identifier.
     * @param string                 $name          Human-readable token name.
     * @param array<string>          $abilities     Allowed ability scopes.
     * @param DateTimeInterface|null $expiresAt     Optional token expiration time.
     * @return NewAccessToken
     */
    public function createToken(
        string $tokenableType,
        int|string $tokenableId,
        string $name,
        array $abilities = ['*'],
        ?DateTimeInterface $expiresAt = null
    ): NewAccessToken {
        $entropy = bin2hex(random_bytes(32));
        $plainTextToken = 'nfp_' . $entropy;
        $hashedToken = hash('sha256', $plainTextToken);

        $now = date('Y-m-d H:i:s');
        $expires = $expiresAt ? $expiresAt->format('Y-m-d H:i:s') : null;

        $token = new PersonalAccessToken([
            'tokenable_type' => $tokenableType,
            'tokenable_id'   => $tokenableId,
            'name'           => $name,
            'token'          => $hashedToken,
            'abilities'      => json_encode(array_values($abilities)),
            'last_used_at'   => null,
            'expires_at'     => $expires,
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);

        $token->save();

        return new NewAccessToken($token, $plainTextToken);
    }

    /**
     * Find an active token by its plain-text representation.
     *
     * @param string $plainTextToken
     * @param bool   $touchLastUsed Whether to update last_used_at timestamp.
     * @return PersonalAccessToken|null
     */
    public function findToken(string $plainTextToken, bool $touchLastUsed = true): ?PersonalAccessToken
    {
        $hashedToken = hash('sha256', $plainTextToken);

        $row = PersonalAccessToken::query()->where('token', $hashedToken)->first();
        if (!$row) {
            return null;
        }

        $token = PersonalAccessToken::newFromBuilder($row);

        if ($token->isExpired()) {
            return null;
        }

        if ($touchLastUsed) {
            $now = date('Y-m-d H:i:s');
            PersonalAccessToken::query()
                ->where('id', $token->getKey())
                ->update([
                    'last_used_at' => $now,
                    'updated_at'   => $now,
                ]);
            $token->setAttribute('last_used_at', $now);
        }

        return $token;
    }

    /**
     * Revoke a token by its ID.
     *
     * @param int|string $id
     * @return bool
     */
    public function revokeToken(int|string $id): bool
    {
        return PersonalAccessToken::query()->where('id', $id)->delete() > 0;
    }

    /**
     * Revoke all tokens belonging to an entity.
     *
     * @param string     $type
     * @param int|string $id
     * @return int Number of deleted tokens
     */
    public function revokeAllFor(string $type, int|string $id): int
    {
        return PersonalAccessToken::query()
            ->where('tokenable_type', $type)
            ->where('tokenable_id', $id)
            ->delete();
    }

    /**
     * Get all tokens for an entity.
     *
     * @param string     $type
     * @param int|string $id
     * @return array<PersonalAccessToken>
     */
    public function getTokensFor(string $type, int|string $id): array
    {
        $rows = PersonalAccessToken::query()
            ->where('tokenable_type', $type)
            ->where('tokenable_id', $id)
            ->get();

        return array_map([PersonalAccessToken::class, 'newFromBuilder'], $rows);
    }
}
