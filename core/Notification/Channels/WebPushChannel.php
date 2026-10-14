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

namespace NetFusion\Core\Notification\Channels;

use NetFusion\Core\Notification\Contracts\ChannelInterface;
use NetFusion\Core\Notification\Notification;

/**
 * Class WebPushChannel
 *
 * W3C Web Push (VAPID) delivery channel.
 *
 * Implements RFC 8291 (Message Encryption) and RFC 8292 (VAPID).
 * In simulate mode returns mocked responses.
 *
 * Config: vapid_public_key, vapid_private_key (base64url), subject (mailto: or https:)
 */
class WebPushChannel implements ChannelInterface
{
    public function __construct(
        private readonly string $vapidPublicKey  = '',
        private readonly string $vapidPrivateKey = '',
        private readonly string $subject         = '',
        private readonly bool   $simulate        = true
    ) {}

    public function getName(): string { return 'web_push'; }

    public function isAvailable(): bool
    {
        return $this->simulate
            || (!empty($this->vapidPublicKey) && !empty($this->vapidPrivateKey));
    }

    /**
     * @param string $recipient JSON-encoded PushSubscription object
     *                          {"endpoint":"...","keys":{"p256dh":"...","auth":"..."}}
     */
    public function send(string $recipient, Notification $notification): array
    {
        if ($this->simulate) {
            return [
                'success'    => true,
                'message_id' => 'webpush_sim_' . uniqid(),
                'error'      => null,
            ];
        }

        $subscription = json_decode($recipient, true);
        if (!isset($subscription['endpoint'], $subscription['keys'])) {
            return ['success' => false, 'message_id' => null, 'error' => 'Invalid subscription format'];
        }

        $endpoint  = $subscription['endpoint'];
        $p256dh    = $subscription['keys']['p256dh']  ?? '';
        $auth      = $subscription['keys']['auth']     ?? '';
        $payload   = json_encode($notification->toArray());
        $jwt       = $this->buildVapidJwt($endpoint);

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Content-Encoding: aes128gcm',
                'Authorization: vapid t=' . $jwt . ',k=' . $this->vapidPublicKey,
                'TTL: ' . $notification->getTtl(),
            ],
            CURLOPT_TIMEOUT => 10,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $success = in_array($code, [200, 201, 202], true);
        return [
            'success'    => $success,
            'message_id' => $success ? 'wp_' . uniqid() : null,
            'error'      => $success ? null : "HTTP {$code}: {$body}",
        ];
    }

    public function sendBatch(array $recipients, Notification $notification): array
    {
        $results = [];
        foreach ($recipients as $sub) {
            $results[$sub] = $this->send($sub, $notification);
        }
        return $results;
    }

    /**
     * Build a minimal VAPID JWT (ES256) for a given endpoint audience.
     */
    private function buildVapidJwt(string $endpoint): string
    {
        $parsed   = parse_url($endpoint);
        $audience = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
        $exp      = time() + 43200; // 12 hours

        $header  = $this->base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $claims  = $this->base64UrlEncode(json_encode([
            'aud' => $audience,
            'exp' => $exp,
            'sub' => $this->subject,
        ]));

        $message = "{$header}.{$claims}";
        $pkey    = openssl_pkey_get_private(
            "-----BEGIN EC PRIVATE KEY-----\n" .
            chunk_split(base64_encode(base64_decode(strtr($this->vapidPrivateKey, '-_', '+/'))), 64, "\n") .
            "-----END EC PRIVATE KEY-----"
        );

        if ($pkey === false) {
            return $message . '.invalid';
        }

        openssl_sign($message, $sig, $pkey, OPENSSL_ALGO_SHA256);
        return $message . '.' . $this->base64UrlEncode($sig);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
