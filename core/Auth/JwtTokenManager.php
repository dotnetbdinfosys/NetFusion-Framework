<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Auth/JwtTokenManager.php
| Module    : Core\Auth
| Namespace : NetFusion\Core\Auth
| Purpose   : Pure-PHP HS256 JWT Token generation, parsing, validation and revocation.
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

use InvalidArgumentException;
use RuntimeException;

/**
 * NetFusion JWT Token Manager
 *
 * Pure-PHP implementation of HS256 JSON Web Tokens.
 * Zero external dependencies — uses PHP's built-in hash_hmac().
 *
 * Supported claims:
 *   - sub    : Subject (user ID)
 *   - iat    : Issued At
 *   - exp    : Expiration Time
 *   - jti    : JWT ID (unique token identifier for revocation)
 *   - roles  : User roles array
 *   - perms  : User permissions array
 *   + Custom claims
 *
 * @package NetFusion\Core\Auth
 * @version 1.0.0
 */
class JwtTokenManager
{
    /**
     * HMAC algorithm.
     */
    protected string $algorithm = 'sha256';

    /**
     * Revoked JTI set (in-memory; replace with Redis/DB in production).
     *
     * @var array<string, true>
     */
    protected array $revokedJtis = [];

    /**
     * @param string $secret     HS256 signing secret (from JWT_SECRET env).
     * @param int    $ttl        Token TTL in seconds (default: 3600 = 1 hour).
     * @param int    $refreshTtl Refresh Token TTL in seconds (default: 30 days).
     */
    public function __construct(
        protected readonly string $secret,
        protected readonly int    $ttl        = 3600,
        protected readonly int    $refreshTtl = 2592000,
    ) {
        if (strlen($this->secret) < 32) {
            throw new InvalidArgumentException(
                'JWT secret must be at least 32 characters long for HS256 security.'
            );
        }
    }

    // =========================================================================
    //  Token Generation
    // =========================================================================

    /**
     * Generate a signed JWT access token for a user.
     *
     * @param  int|string        $userId      Subject (user ID).
     * @param  array<int,string> $roles       User roles.
     * @param  array<int,string> $permissions User permissions.
     * @param  array<string,mixed> $custom    Additional custom claims.
     * @return string                         Signed JWT string.
     */
    public function generate(
        int|string $userId,
        array      $roles       = [],
        array      $permissions = [],
        array      $custom      = [],
    ): string {
        $now = time();
        $jti = bin2hex(random_bytes(16));

        $payload = array_merge($custom, [
            'sub'   => (string) $userId,
            'iat'   => $now,
            'exp'   => $now + $this->ttl,
            'jti'   => $jti,
            'roles' => $roles,
            'perms' => $permissions,
        ]);

        return $this->encode($payload);
    }

    /**
     * Generate a long-lived refresh token.
     *
     * @param  int|string $userId Subject (user ID).
     * @return string             Signed refresh JWT.
     */
    public function generateRefreshToken(int|string $userId): string
    {
        $now = time();

        $payload = [
            'sub'     => (string) $userId,
            'iat'     => $now,
            'exp'     => $now + $this->refreshTtl,
            'jti'     => bin2hex(random_bytes(16)),
            'type'    => 'refresh',
        ];

        return $this->encode($payload);
    }

    // =========================================================================
    //  Token Parsing & Validation
    // =========================================================================

    /**
     * Parse and validate a JWT token string.
     *
     * Returns the decoded payload array on success.
     * Throws RuntimeException on any validation failure.
     *
     * @param  string              $token  The raw JWT string.
     * @return array<string,mixed>         Decoded payload.
     *
     * @throws RuntimeException If token is malformed, expired, revoked, or signature invalid.
     */
    public function parse(string $token): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new RuntimeException('JWT is malformed: expected 3 segments.');
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

        // Verify signature
        $signingInput  = $encodedHeader . '.' . $encodedPayload;
        $expectedSig   = $this->sign($signingInput);
        $providedSig   = $this->base64UrlDecode($encodedSignature);

        if (!hash_equals($expectedSig, $providedSig)) {
            throw new RuntimeException('JWT signature verification failed.');
        }

        // Decode payload
        $payload = json_decode($this->base64UrlDecode($encodedPayload), true);

        if (!is_array($payload)) {
            throw new RuntimeException('JWT payload is invalid JSON.');
        }

        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new RuntimeException('JWT has expired.');
        }

        // Check revocation
        $jti = $payload['jti'] ?? null;
        if ($jti !== null && isset($this->revokedJtis[$jti])) {
            throw new RuntimeException('JWT has been revoked.');
        }

        return $payload;
    }

    /**
     * Validate a token silently — returns null instead of throwing.
     *
     * @param  string                   $token JWT string.
     * @return array<string,mixed>|null        Payload or null if invalid.
     */
    public function validate(string $token): ?array
    {
        try {
            return $this->parse($token);
        } catch (RuntimeException) {
            return null;
        }
    }

    /**
     * Refresh a valid access token, extending its expiry.
     *
     * @param  string $token The existing JWT.
     * @return string        A new JWT with updated iat/exp/jti.
     */
    public function refresh(string $token): string
    {
        $payload = $this->parse($token);

        // Revoke old JTI
        if (isset($payload['jti'])) {
            $this->revoke($payload['jti']);
        }

        $now = time();

        $payload['iat'] = $now;
        $payload['exp'] = $now + $this->ttl;
        $payload['jti'] = bin2hex(random_bytes(16));

        return $this->encode($payload);
    }

    /**
     * Revoke a token by its JTI claim.
     *
     * @param string $jti The unique token identifier.
     */
    public function revoke(string $jti): void
    {
        $this->revokedJtis[$jti] = true;
    }

    /**
     * Revoke a token directly (parses JTI from token string).
     */
    public function revokeToken(string $token): void
    {
        try {
            $payload = $this->parse($token);
            if (isset($payload['jti'])) {
                $this->revoke($payload['jti']);
            }
        } catch (RuntimeException) {
            // Token already invalid — nothing to revoke
        }
    }

    // =========================================================================
    //  Internal Encoding Helpers
    // =========================================================================

    /**
     * Encode a payload array as a signed JWT string.
     *
     * @param  array<string,mixed> $payload
     * @return string
     */
    protected function encode(array $payload): string
    {
        $header = $this->base64UrlEncode(json_encode([
            'typ' => 'JWT',
            'alg' => 'HS256',
        ]));

        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));
        $signingInput   = $header . '.' . $payloadEncoded;
        $signature      = $this->base64UrlEncode($this->sign($signingInput));

        return $signingInput . '.' . $signature;
    }

    /**
     * HMAC-SHA256 sign a string.
     */
    protected function sign(string $input): string
    {
        return hash_hmac($this->algorithm, $input, $this->secret, true);
    }

    /**
     * Base64Url encode (RFC 4648 §5).
     */
    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64Url decode (RFC 4648 §5).
     */
    protected function base64UrlDecode(string $data): string
    {
        $padded = str_pad(strtr($data, '-_', '+/'), strlen($data) % 4 === 0 ? strlen($data) : strlen($data) + (4 - strlen($data) % 4), '=');
        $decoded = base64_decode($padded, true);

        if ($decoded === false) {
            throw new RuntimeException('JWT segment could not be base64url decoded.');
        }

        return $decoded;
    }

    /**
     * Get token TTL in seconds.
     */
    public function getTtl(): int
    {
        return $this->ttl;
    }
}
