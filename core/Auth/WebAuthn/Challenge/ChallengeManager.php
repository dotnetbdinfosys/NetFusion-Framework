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

namespace NetFusion\Core\Auth\WebAuthn\Challenge;

class ChallengeManager
{
    /** @var array<string, array{type: string, user_id: string|int|null, expires_at: int}> */
    protected array $challenges = [];

    public function generate(
        string $type = 'register',
        string|int|null $userId = null,
        int $ttlSeconds = 300,
        int $length = 32
    ): string {
        $raw = random_bytes($length);
        $challenge = self::base64UrlEncode($raw);

        $this->challenges[$challenge] = [
            'type' => $type,
            'user_id' => $userId,
            'expires_at' => time() + $ttlSeconds,
        ];

        return $challenge;
    }

    public function validate(string $challenge, string $type = 'register', bool $consume = true): bool
    {
        if (!isset($this->challenges[$challenge])) {
            return false;
        }

        $item = $this->challenges[$challenge];

        if ($item['expires_at'] < time()) {
            unset($this->challenges[$challenge]);
            return false;
        }

        if ($item['type'] !== $type) {
            return false;
        }

        if ($consume) {
            unset($this->challenges[$challenge]);
        }

        return true;
    }

    public function consume(string $challenge): bool
    {
        if (isset($this->challenges[$challenge])) {
            unset($this->challenges[$challenge]);
            return true;
        }
        return false;
    }

    public function prune(): int
    {
        $now = time();
        $pruned = 0;
        foreach ($this->challenges as $c => $data) {
            if ($data['expires_at'] < $now) {
                unset($this->challenges[$c]);
                $pruned++;
            }
        }
        return $pruned;
    }

    public static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder !== 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/')) ?: '';
    }
}
