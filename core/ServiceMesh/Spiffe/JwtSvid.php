<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
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

declare(strict_types=1);

namespace NetFusion\Core\ServiceMesh\Spiffe;

use InvalidArgumentException;
use RuntimeException;

/**
 * Class JwtSvid
 *
 * Implements the SPIFFE JWT-SVID Specification.
 * Used for L7 HTTP token-based authentication and service attestation.
 */
class JwtSvid
{
    private readonly SpiffeId $spiffeId;
    private readonly array $audience;
    private readonly int $issuedAt;
    private readonly int $expiresAt;
    private readonly array $claims;

    public function __construct(
        private readonly string $token,
        array $payload
    ) {
        $sub = $payload['sub'] ?? '';
        if (!SpiffeId::isValid($sub)) {
            throw new InvalidArgumentException("JWT 'sub' claim is not a valid SPIFFE ID: '{$sub}'");
        }

        $this->spiffeId = new SpiffeId($sub);
        $this->audience = is_array($payload['aud'] ?? []) ? $payload['aud'] : [$payload['aud'] ?? ''];
        $this->issuedAt = (int) ($payload['iat'] ?? 0);
        $this->expiresAt = (int) ($payload['exp'] ?? 0);
        $this->claims = $payload;
    }

    public static function create(
        SpiffeId $spiffeId,
        array|string $audience,
        int $ttlSeconds,
        string $signingKey,
        string $algorithm = 'HS256',
        array $extraClaims = []
    ): string {
        $now = time();
        $aud = is_array($audience) ? array_values($audience) : [$audience];

        $header = [
            'alg' => $algorithm,
            'typ' => 'JWT',
        ];

        $payload = array_merge($extraClaims, [
            'sub' => (string) $spiffeId,
            'aud' => $aud,
            'iat' => $now,
            'exp' => $now + $ttlSeconds,
        ]);

        $b64Header = self::base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $b64Payload = self::base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signatureInput = "{$b64Header}.{$b64Payload}";

        if ($algorithm === 'HS256') {
            $sig = hash_hmac('sha256', $signatureInput, $signingKey, true);
        } elseif ($algorithm === 'RS256') {
            $pkey = openssl_pkey_get_private($signingKey);
            if ($pkey === false) {
                throw new RuntimeException("Invalid private key for RS256 signing.");
            }
            openssl_sign($signatureInput, $sig, $pkey, OPENSSL_ALGO_SHA256);
        } else {
            throw new InvalidArgumentException("Unsupported signing algorithm: {$algorithm}");
        }

        return "{$signatureInput}." . self::base64UrlEncode($sig);
    }

    public static function parseAndVerify(
        string $token,
        string $verificationKey,
        string $expectedAudience,
        string $algorithm = 'HS256'
    ): self {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new InvalidArgumentException("Malformed JWT SVID token.");
        }

        [$b64Header, $b64Payload, $b64Sig] = $parts;
        $signatureInput = "{$b64Header}.{$b64Payload}";
        $signature = self::base64UrlDecode($b64Sig);

        if ($algorithm === 'HS256') {
            $expectedSig = hash_hmac('sha256', $signatureInput, $verificationKey, true);
            if (!hash_equals($expectedSig, $signature)) {
                throw new RuntimeException("JWT SVID signature verification failed.");
            }
        } elseif ($algorithm === 'RS256') {
            $pkey = openssl_pkey_get_public($verificationKey);
            if ($pkey === false) {
                throw new RuntimeException("Invalid public key for RS256 verification.");
            }
            $result = openssl_verify($signatureInput, $signature, $pkey, OPENSSL_ALGO_SHA256);
            if ($result !== 1) {
                throw new RuntimeException("JWT SVID asymmetric signature verification failed.");
            }
        } else {
            throw new InvalidArgumentException("Unsupported algorithm: {$algorithm}");
        }

        $payload = json_decode(self::base64UrlDecode($b64Payload), true, 512, JSON_THROW_ON_ERROR);

        // Check expiration
        if (($payload['exp'] ?? 0) < time()) {
            throw new RuntimeException("JWT SVID has expired.");
        }

        // Check audience
        $auds = is_array($payload['aud'] ?? []) ? $payload['aud'] : [$payload['aud'] ?? ''];
        if (!in_array($expectedAudience, $auds, true)) {
            throw new RuntimeException("JWT SVID audience mismatch. Expected '{$expectedAudience}'.");
        }

        return new self($token, $payload);
    }

    public function getSpiffeId(): SpiffeId
    {
        return $this->spiffeId;
    }

    public function getAudience(): array
    {
        return $this->audience;
    }

    public function getIssuedAt(): int
    {
        return $this->issuedAt;
    }

    public function getExpiresAt(): int
    {
        return $this->expiresAt;
    }

    public function getClaims(): array
    {
        return $this->claims;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
