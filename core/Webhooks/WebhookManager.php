<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Webhooks/WebhookManager.php
| Module    : Core\Webhooks
| Namespace : NetFusion\Core\Webhooks
| Purpose   : Central webhook management, topic matching, queue dispatch, and audit logging.
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

namespace NetFusion\Core\Webhooks;

use NetFusion\Core\Application;
use NetFusion\Core\Database\DatabaseManager;

/**
 * Class WebhookManager
 *
 * Central dispatcher and repository for outgoing webhook subscriptions and delivery audits.
 *
 * @package NetFusion\Core\Webhooks
 */
class WebhookManager
{
    protected WebhookSigner $signer;
    protected WebhookClient $client;

    public function __construct(
        protected Application $app
    ) {
        $this->signer = new WebhookSigner();
        $this->client = new WebhookClient($this->signer);
    }

    public function getSigner(): WebhookSigner
    {
        return $this->signer;
    }

    public function getClient(): WebhookClient
    {
        return $this->client;
    }

    /**
     * Subscribe a target URL to an event topic.
     *
     * @param string $topic Event name or wildcard (e.g., "billing.invoice.paid", "payroll.*", "*").
     * @param string $targetUrl
     * @param string|null $secret If null, a secure 32-character random secret is generated.
     * @param int $retryLimit
     * @return array{id: int, topic: string, target_url: string, secret: string}
     */
    public function subscribe(string $topic, string $targetUrl, ?string $secret = null, int $retryLimit = 3): array
    {
        $secret = $secret ?: bin2hex(random_bytes(16));

        $db = $this->app->make(DatabaseManager::class)->connection();
        $id = (int) $db->insert(
            "INSERT INTO netfusion_webhook_subscriptions (topic, target_url, secret, retry_limit, is_active, created_at)
             VALUES (:topic, :url, :secret, :retries, 1, :now)",
            [
                ':topic'   => $topic,
                ':url'     => $targetUrl,
                ':secret'  => $secret,
                ':retries' => $retryLimit,
                ':now'     => date('Y-m-d H:i:s'),
            ]
        );

        return [
            'id'         => $id,
            'topic'      => $topic,
            'target_url' => $targetUrl,
            'secret'     => $secret,
        ];
    }

    /**
     * Dispatch an event to all subscribed webhooks.
     *
     * @param string $event
     * @param array<string, mixed> $data
     * @param bool $sync Force immediate synchronous delivery.
     * @return array<int, array<string, mixed>> Delivery results for each matching subscription.
     */
    public function dispatch(string $event, array $data, bool $sync = false): array
    {
        $subscriptions = $this->getSubscriptionsForEvent($event);
        if (empty($subscriptions)) {
            return [];
        }

        $results = [];
        $payload = new WebhookPayload($event, $data);

        foreach ($subscriptions as $sub) {
            $subId = (int) $sub['id'];
            $url = (string) $sub['target_url'];
            $secret = (string) $sub['secret'];

            if ($sync) {
                $res = $this->client->send($url, $payload, $secret);
                $this->logDelivery($subId, $payload, $res);
                $results[] = [
                    'subscription_id' => $subId,
                    'url'             => $url,
                    'result'          => $res,
                ];
            } else {
                // If queue manager is available, dispatch as queue job or execute synchronous fallback
                if ($this->app->has('queue')) {
                    $this->app->make('queue')->push(function () use ($subId, $url, $secret, $payload) {
                        $res = $this->client->send($url, $payload, $secret);
                        $this->logDelivery($subId, $payload, $res);
                    }, 'webhooks');
                    $results[] = [
                        'subscription_id' => $subId,
                        'url'             => $url,
                        'queued'          => true,
                    ];
                } else {
                    $res = $this->client->send($url, $payload, $secret);
                    $this->logDelivery($subId, $payload, $res);
                    $results[] = [
                        'subscription_id' => $subId,
                        'url'             => $url,
                        'result'          => $res,
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * Log a delivery attempt in the database.
     *
     * @param int $subscriptionId
     * @param WebhookPayload $payload
     * @param array{status: int, response: string, duration_ms: float, error: ?string} $result
     */
    public function logDelivery(int $subscriptionId, WebhookPayload $payload, array $result): void
    {
        try {
            $db = $this->app->make(DatabaseManager::class)->connection();
            $db->insert(
                "INSERT INTO netfusion_webhook_deliveries 
                 (subscription_id, delivery_id, event, payload, status_code, response_body, error_message, duration_ms, attempt, created_at)
                 VALUES (:sub_id, :del_id, :event, :payload, :status, :resp, :err, :duration, :attempt, :now)",
                [
                    ':sub_id'   => $subscriptionId,
                    ':del_id'   => $payload->getId(),
                    ':event'    => $payload->getEvent(),
                    ':payload'  => $payload->toJson(),
                    ':status'   => $result['status'],
                    ':resp'     => substr((string) $result['response'], 0, 1000),
                    ':err'      => $result['error'],
                    ':duration' => $result['duration_ms'],
                    ':attempt'  => $payload->getAttempt(),
                    ':now'      => date('Y-m-d H:i:s'),
                ]
            );
        } catch (\Throwable) {
            // Silently preserve stability if audit table is temporarily unavailable
        }
    }

    /**
     * Retrieve all active subscriptions matching the given event name.
     *
     * @param string $event
     * @return array<int, array<string, mixed>>
     */
    public function getSubscriptionsForEvent(string $event): array
    {
        try {
            $db = $this->app->make(DatabaseManager::class)->connection();
            $all = $db->select("SELECT * FROM netfusion_webhook_subscriptions WHERE is_active = 1");

            $matched = [];
            foreach ($all as $row) {
                if ($this->matchTopic((string) $row['topic'], $event)) {
                    $matched[] = $row;
                }
            }

            return $matched;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Match a topic pattern against an event. Supports wildcards ('*', 'billing.*').
     */
    public function matchTopic(string $pattern, string $event): bool
    {
        if ($pattern === '*' || $pattern === $event) {
            return true;
        }

        if (str_ends_with($pattern, '.*')) {
            $prefix = substr($pattern, 0, -2);
            return str_starts_with($event, $prefix . '.');
        }

        return false;
    }

    /**
     * Verify incoming signature.
     */
    public function verify(string $signatureHeader, string $payload, string $secret, int $tolerance = 300): bool
    {
        return $this->signer->verify($signatureHeader, $payload, $secret, $tolerance);
    }
}
