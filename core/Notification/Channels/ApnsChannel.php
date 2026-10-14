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
 * Class ApnsChannel
 *
 * Apple Push Notification Service (APNs) channel.
 *
 * Uses HTTP/2 + JWT (Provider Authentication Token) to deliver
 * notifications to Apple devices. In simulate mode returns mocked responses.
 *
 * Requires: team_id, key_id, private_key (ES256 PEM), bundle_id
 */
class ApnsChannel implements ChannelInterface
{
    private const APNS_HOST_PROD    = 'api.push.apple.com';
    private const APNS_HOST_SANDBOX = 'api.sandbox.push.apple.com';
    private const APNS_PORT         = 443;

    private ?string $cachedToken    = null;
    private int     $tokenExpiresAt = 0;

    public function __construct(
        private readonly string $teamId     = '',
        private readonly string $keyId      = '',
        private readonly string $privateKey = '', // PEM format ES256 private key
        private readonly string $bundleId   = '',
        private readonly bool   $sandbox    = false,
        private readonly bool   $simulate   = true
    ) {}

    public function getName(): string { return 'apns'; }

    public function isAvailable(): bool
    {
        return $this->simulate
            || (!empty($this->teamId) && !empty($this->keyId) && !empty($this->privateKey));
    }

    public function send(string $recipient, Notification $notification): array
    {
        if ($this->simulate) {
            return [
                'success'    => true,
                'message_id' => 'apns_sim_' . uniqid(),
                'error'      => null,
            ];
        }

        // Build JWT provider token
        $token   = $this->getJwt();
        $payload = json_encode($notification->toApnsPayload());
        $host    = $this->sandbox ? self::APNS_HOST_SANDBOX : self::APNS_HOST_PROD;
        $url     = "https://{$host}/3/device/{$recipient}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_2_0,
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'authorization: bearer ' . $token,
                'apns-topic: ' . $this->bundleId,
                'apns-push-type: alert',
                'apns-priority: 10',
                'content-type: application/json',
            ],
            CURLOPT_TIMEOUT => 10,
        ]);
        $body    = curl_exec($ch);
        $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headers = curl_getinfo($ch);
        curl_close($ch);

        if ($code === 200) {
            return ['success' => true, 'message_id' => $headers['apns_id'] ?? uniqid(), 'error' => null];
        }
        $err = json_decode($body, true);
        return ['success' => false, 'message_id' => null, 'error' => $err['reason'] ?? "HTTP {$code}"];
    }

    public function sendBatch(array $recipients, Notification $notification): array
    {
        $results = [];
        foreach ($recipients as $token) {
            $results[$token] = $this->send($token, $notification);
        }
        return $results;
    }

    /**
     * Generate JWT provider token (cached for 55 minutes).
     */
    private function getJwt(): string
    {
        if ($this->cachedToken && time() < $this->tokenExpiresAt) {
            return $this->cachedToken;
        }

        $issuedAt = time();
        $header   = $this->base64UrlEncode(json_encode(['alg' => 'ES256', 'kid' => $this->keyId]));
        $claims   = $this->base64UrlEncode(json_encode(['iss' => $this->teamId, 'iat' => $issuedAt]));
        $message  = "{$header}.{$claims}";

        $pkey = openssl_pkey_get_private($this->privateKey);
        openssl_sign($message, $signature, $pkey, OPENSSL_ALGO_SHA256);

        $this->cachedToken    = $message . '.' . $this->base64UrlEncode($signature);
        $this->tokenExpiresAt = $issuedAt + 3300; // 55 minutes
        return $this->cachedToken;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
