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
 * Class FcmChannel
 *
 * Firebase Cloud Messaging (FCM) v1 API delivery channel.
 *
 * In production: sends real HTTP POST to FCM HTTP v1 API using
 * a service-account OAuth2 Bearer token.
 *
 * In simulate mode (default for tests): returns a mocked successful
 * response without making any real HTTP calls.
 */
class FcmChannel implements ChannelInterface
{
    public function __construct(
        private readonly string $projectId   = '',
        private readonly string $accessToken = '',
        private readonly bool   $simulate    = true
    ) {}

    public function getName(): string { return 'fcm'; }

    public function isAvailable(): bool
    {
        return $this->simulate || (!empty($this->projectId) && !empty($this->accessToken));
    }

    public function send(string $recipient, Notification $notification): array
    {
        if ($this->simulate) {
            return [
                'success'    => true,
                'message_id' => 'fcm_sim_' . uniqid(),
                'error'      => null,
            ];
        }

        $payload = $notification->toFcmPayload($recipient);
        $url     = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->accessToken,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 10,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $code !== 200) {
            return ['success' => false, 'message_id' => null, 'error' => "HTTP {$code}"];
        }

        $response = json_decode($body, true);
        return [
            'success'    => isset($response['name']),
            'message_id' => $response['name'] ?? null,
            'error'      => $response['error']['message'] ?? null,
        ];
    }

    public function sendBatch(array $recipients, Notification $notification): array
    {
        $results = [];
        foreach ($recipients as $token) {
            $results[$token] = $this->send($token, $notification);
        }
        return $results;
    }
}
