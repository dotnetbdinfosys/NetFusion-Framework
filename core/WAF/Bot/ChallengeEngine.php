<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\WAF\Bot;

class ChallengeEngine
{
    /**
     * @param string $secret HMAC signing secret for challenges
     */
    public function __construct(
        protected string $secret = 'netfusion-waf-challenge-secret-key-32b'
    ) {
    }

    /**
     * Create a Proof-of-Work (PoW) puzzle challenge.
     *
     * @param int $difficulty Number of leading zeros required (default 4)
     * @return array{seed: string, difficulty: int, signature: string, timestamp: int}
     */
    public function createPoWChallenge(int $difficulty = 4): array
    {
        $seed = bin2hex(random_bytes(16));
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$seed}:{$difficulty}:{$timestamp}", $this->secret);

        return [
            'seed' => $seed,
            'difficulty' => $difficulty,
            'signature' => $signature,
            'timestamp' => $timestamp,
        ];
    }

    /**
     * Verify a Proof-of-Work (PoW) solution.
     */
    public function verifyPoW(string $seed, int $difficulty, int $timestamp, string $signature, string|int $nonce): bool
    {
        // 1. Check challenge age (max 5 minutes)
        if (time() - $timestamp > 300) {
            return false;
        }

        // 2. Verify challenge signature integrity
        $expectedSig = hash_hmac('sha256', "{$seed}:{$difficulty}:{$timestamp}", $this->secret);
        if (!hash_equals($expectedSig, $signature)) {
            return false;
        }

        // 3. Verify SHA-256 leading zeros
        $hash = hash('sha256', $seed . (string)$nonce);
        $prefix = str_repeat('0', $difficulty);

        return str_starts_with($hash, $prefix);
    }

    /**
     * Generate a signed JS validation cookie for legitimate browsers.
     */
    public function generateBrowserToken(string $clientIp, int $ttl = 3600): string
    {
        $expiry = time() + $ttl;
        $payload = "{$clientIp}:{$expiry}";
        $sig = hash_hmac('sha256', $payload, $this->secret);

        return base64_encode("{$payload}:{$sig}");
    }

    /**
     * Verify signed JS browser cookie token.
     */
    public function verifyBrowserToken(string $token, string $clientIp): bool
    {
        $decoded = base64_decode($token, true);
        if ($decoded === false) {
            return false;
        }

        $parts = explode(':', $decoded);
        if (count($parts) !== 3) {
            return false;
        }

        [$tokenIp, $expiry, $sig] = $parts;

        if ($tokenIp !== $clientIp) {
            return false;
        }

        if ((int)$expiry < time()) {
            return false;
        }

        $expectedSig = hash_hmac('sha256', "{$tokenIp}:{$expiry}", $this->secret);
        return hash_equals($expectedSig, $sig);
    }
}
