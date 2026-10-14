<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Notifications/Channels/WebhookChannel.php
| Module    : Notifications\Channels
| Namespace : NetFusion\Core\Notifications\Channels
| Purpose   : Dispatches notifications via outgoing HTTP Webhooks (Slack, Discord, Custom).
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

namespace NetFusion\Core\Notifications\Channels;

use NetFusion\Core\Application;
use NetFusion\Core\Notifications\Contracts\NotificationChannelInterface;
use NetFusion\Core\Notifications\Notification;
use RuntimeException;

/**
 * Class WebhookChannel
 *
 * Dispatches outgoing webhook notification payloads via HTTP POST.
 * Compatible with Slack incoming webhooks, Discord webhooks, and REST endpoints.
 *
 * @package NetFusion\Core\Notifications\Channels
 */
class WebhookChannel implements NotificationChannelInterface
{
    /**
     * @param Application|null $app
     * @param int              $timeout Request timeout in seconds
     */
    public function __construct(
        protected ?Application $app = null,
        protected int $timeout = 5
    ) {
        $this->app = $app ?? (function_exists('app') ? app() : null);
    }

    /**
     * {@inheritdoc}
     */
    public function send(object $notifiable, Notification $notification): mixed
    {
        if (! method_exists($notification, 'toWebhook')) {
            return null;
        }

        $payload = $notification->toWebhook($notifiable);
        $url = $this->resolveWebhookUrl($notifiable, $payload);

        if ($url === null || $url === '') {
            return null;
        }

        $bodyData = is_array($payload) ? ($payload['data'] ?? $payload) : ['message' => (string) $payload];
        // Remove url key if it was in the root payload
        unset($bodyData['url']);

        $jsonPayload = json_encode($bodyData, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $headers = [
            'Content-Type: application/json',
            'User-Agent: NetFusion-Notification-Gateway/1.0',
            'X-NetFusion-Notification-Id: ' . $notification->id,
        ];

        // Optional HMAC signature if secret is provided
        if (isset($payload['secret']) && is_string($payload['secret']) && $payload['secret'] !== '') {
            $timestamp = time();
            $signature = hash_hmac('sha256', "{$timestamp}.{$jsonPayload}", $payload['secret']);
            $headers[] = "X-NetFusion-Timestamp: {$timestamp}";
            $headers[] = "X-NetFusion-Signature: sha256={$signature}";
        }

        if (isset($payload['headers']) && is_array($payload['headers'])) {
            foreach ($payload['headers'] as $k => $v) {
                $headers[] = is_numeric($k) ? (string) $v : "{$k}: {$v}";
            }
        }

        return $this->postHttp($url, $jsonPayload, $headers);
    }

    /**
     * Resolve target webhook URL.
     *
     * @param object $notifiable
     * @param mixed  $payload
     * @return string|null
     */
    protected function resolveWebhookUrl(object $notifiable, mixed $payload): ?string
    {
        if (is_array($payload) && ! empty($payload['url'])) {
            return (string) $payload['url'];
        }

        if (method_exists($notifiable, 'routeNotificationFor')) {
            $route = $notifiable->routeNotificationFor('webhook');
            if (is_string($route) && $route !== '') {
                return $route;
            }
        }

        return $notifiable->webhook_url ?? ($notifiable->attributes['webhook_url'] ?? null);
    }

    /**
     * Send HTTP POST request with timeout and error capture.
     *
     * @param string        $url
     * @param string        $payload
     * @param array<string> $headers
     * @return array{status: int, response: string}
     */
    protected function postHttp(string $url, string $payload, array $headers): array
    {
        $opts = [
            'http' => [
                'method'        => 'POST',
                'header'        => implode("\r\n", $headers),
                'content'       => $payload,
                'timeout'       => $this->timeout,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ],
        ];

        $context = stream_context_create($opts);
        $result = @file_get_contents($url, false, $context);

        $status = 200;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $line) {
                if (preg_match('#HTTP/\S+\s+(\d+)#', $line, $matches)) {
                    $status = (int) $matches[1];
                    break;
                }
            }
        }

        return [
            'status'   => $status,
            'response' => (string) $result,
        ];
    }
}
