<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Webhooks/Incoming/WebhookGateway.php
| Module    : Webhooks
| Namespace : NetFusion\Core\Webhooks\Incoming
| Purpose   : Secure incoming webhook ingestion, idempotency deduplication, persistence & event dispatch.
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

namespace NetFusion\Core\Webhooks\Incoming;

use NetFusion\Core\Application;
use NetFusion\Core\Database\DatabaseManager;
use NetFusion\Core\Events\Dispatcher;
use NetFusion\Core\Webhooks\Contracts\IncomingWebhookVerifierInterface;
use RuntimeException;
use Throwable;

/**
 * Class WebhookGateway
 *
 * Enterprise Incoming Webhook Gateway with cryptographic signature verification,
 * replay attack mitigation, persistent ledger, idempotency deduplication,
 * and internal event bus dispatch.
 *
 * @package NetFusion\Core\Webhooks\Incoming
 */
class WebhookGateway
{
    protected IncomingWebhookVerifierInterface $verifier;

    /**
     * Pre-configured gateway secrets.
     *
     * @var array<string, string>
     */
    protected array $secrets = [];

    /**
     * @param Application $app
     * @param IncomingWebhookVerifierInterface|null $verifier
     */
    public function __construct(
        protected Application $app,
        ?IncomingWebhookVerifierInterface $verifier = null
    ) {
        $this->verifier = $verifier ?? new WebhookVerifier();
    }

    /**
     * Register a secret for a specific webhook gateway.
     */
    public function registerSecret(string $gateway, string $secret): self
    {
        $this->secrets[strtolower(trim($gateway))] = $secret;
        return $this;
    }

    /**
     * Retrieve the active verifier instance.
     */
    public function getVerifier(): IncomingWebhookVerifierInterface
    {
        return $this->verifier;
    }

    /**
     * Ingest an incoming webhook request.
     *
     * @param string $gateway Provider name (e.g. 'stripe', 'github', 'shopify', 'custom')
     * @param string $payload Raw payload body
     * @param array<string, mixed> $headers HTTP request headers
     * @param string|null $secret Optional override secret; if null, config/registered secrets are checked
     * @return array{success: bool, duplicate: bool, id: int|null, gateway: string, event_id: ?string, event_type: string, status: string, message: string}
     */
    public function ingest(string $gateway, string $payload, array $headers = [], ?string $secret = null): array
    {
        $gatewayKey = strtolower(trim($gateway));
        $resolvedSecret = $secret ?? $this->resolveSecret($gatewayKey);

        // 1. Signature Verification (if secret is configured)
        if ($resolvedSecret !== null && $resolvedSecret !== '') {
            $isValid = $this->verifier->verify($gatewayKey, $payload, $headers, $resolvedSecret);
            if (!$isValid) {
                return [
                    'success'    => false,
                    'duplicate'  => false,
                    'id'         => null,
                    'gateway'    => $gatewayKey,
                    'event_id'   => null,
                    'event_type' => 'unknown',
                    'status'     => 'invalid_signature',
                    'message'    => "Cryptographic webhook signature verification failed for gateway '{$gatewayKey}'.",
                ];
            }
        }

        // 2. Parse payload data
        $parsed = json_decode($payload, true);
        if (!is_array($parsed)) {
            $parsed = [];
        }

        // 3. Extract event ID and event type based on gateway heuristics
        $eventId = $this->extractEventId($gatewayKey, $parsed, $headers);
        $eventType = $this->extractEventType($gatewayKey, $parsed, $headers);

        $db = $this->app->make(DatabaseManager::class)->connection();

        // 4. Idempotency & Deduplication Check
        if ($eventId !== null && $eventId !== '') {
            $existing = $db->selectOne(
                "SELECT id, status, processed_at FROM incoming_webhooks WHERE gateway = :gw AND event_id = :eid LIMIT 1",
                [':gw' => $gatewayKey, ':eid' => $eventId]
            );

            if ($existing) {
                return [
                    'success'    => true,
                    'duplicate'  => true,
                    'id'         => (int) $existing['id'],
                    'gateway'    => $gatewayKey,
                    'event_id'   => $eventId,
                    'event_type' => $eventType,
                    'status'     => (string) $existing['status'],
                    'message'    => "Webhook event '{$eventId}' has already been recorded (idempotent duplicate).",
                ];
            }
        }

        // 5. Persist record into incoming_webhooks ledger
        $now = date('Y-m-d H:i:s');
        $id = (int) $db->insert(
            "INSERT INTO incoming_webhooks (gateway, event_id, event_type, payload, headers, status, attempts, created_at)
             VALUES (:gw, :eid, :etype, :payload, :headers, 'pending', 0, :created_at)",
            [
                ':gw'         => $gatewayKey,
                ':eid'        => $eventId,
                ':etype'      => $eventType,
                ':payload'    => $payload,
                ':headers'    => json_encode($headers, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ':created_at' => $now,
            ]
        );

        // 6. Dispatch internal events
        $status = 'processed';
        $errorMessage = null;

        try {
            $this->dispatchEvent($gatewayKey, $eventType, $eventId, $parsed, $payload, $id);
        } catch (Throwable $e) {
            $status = 'failed';
            $errorMessage = $e->getMessage();
        }

        // 7. Update status & processed_at
        $db->statement(
            "UPDATE incoming_webhooks 
             SET status = :status, attempts = attempts + 1, error_message = :err, processed_at = :proc
             WHERE id = :id",
            [
                ':status' => $status,
                ':err'    => $errorMessage,
                ':proc'   => date('Y-m-d H:i:s'),
                ':id'     => $id,
            ]
        );

        return [
            'success'    => $status === 'processed',
            'duplicate'  => false,
            'id'         => $id,
            'gateway'    => $gatewayKey,
            'event_id'   => $eventId,
            'event_type' => $eventType,
            'status'     => $status,
            'message'    => $status === 'processed' 
                ? "Webhook successfully processed and dispatched." 
                : "Webhook recorded but failed during event handling: {$errorMessage}",
        ];
    }

    /**
     * Replay a previously recorded webhook by its ledger ID.
     *
     * @param int|string $id
     * @return array{success: bool, id: int, status: string, message: string}
     */
    public function replay(int|string $id): array
    {
        $db = $this->app->make(DatabaseManager::class)->connection();
        $record = $db->selectOne(
            "SELECT * FROM incoming_webhooks WHERE id = :id LIMIT 1",
            [':id' => (int) $id]
        );

        if (!$record) {
            throw new RuntimeException("Incoming webhook record with ID '{$id}' not found.");
        }

        $parsed = json_decode((string) $record['payload'], true);
        if (!is_array($parsed)) {
            $parsed = [];
        }

        $status = 'processed';
        $errorMessage = null;

        try {
            $this->dispatchEvent(
                (string) $record['gateway'],
                (string) $record['event_type'],
                $record['event_id'] !== null ? (string) $record['event_id'] : null,
                $parsed,
                (string) $record['payload'],
                (int) $record['id']
            );
        } catch (Throwable $e) {
            $status = 'failed';
            $errorMessage = $e->getMessage();
        }

        $db->statement(
            "UPDATE incoming_webhooks 
             SET status = :status, attempts = attempts + 1, error_message = :err, processed_at = :proc
             WHERE id = :id",
            [
                ':status' => $status,
                ':err'    => $errorMessage,
                ':proc'   => date('Y-m-d H:i:s'),
                ':id'     => (int) $record['id'],
            ]
        );

        return [
            'success' => $status === 'processed',
            'id'      => (int) $record['id'],
            'status'  => $status,
            'message' => $status === 'processed'
                ? "Webhook {$id} successfully replayed."
                : "Webhook {$id} replay failed: {$errorMessage}",
        ];
    }

    /**
     * Find a recorded incoming webhook by ID.
     */
    public function find(int|string $id): ?array
    {
        $db = $this->app->make(DatabaseManager::class)->connection();
        $record = $db->selectOne(
            "SELECT * FROM incoming_webhooks WHERE id = :id LIMIT 1",
            [':id' => (int) $id]
        );

        return $record ?: null;
    }

    /**
     * List recent incoming webhooks with optional filters.
     *
     * @param int $limit
     * @param string|null $gateway
     * @param string|null $status
     * @return array<int, array<string, mixed>>
     */
    public function getRecent(int $limit = 50, ?string $gateway = null, ?string $status = null): array
    {
        $db = $this->app->make(DatabaseManager::class)->connection();
        $sql = "SELECT * FROM incoming_webhooks WHERE 1=1";
        $params = [];

        if ($gateway !== null && $gateway !== '') {
            $sql .= " AND gateway = :gw";
            $params[':gw'] = strtolower(trim($gateway));
        }

        if ($status !== null && $status !== '') {
            $sql .= " AND status = :st";
            $params[':st'] = strtolower(trim($status));
        }

        $sql .= " ORDER BY id DESC LIMIT " . (int) $limit;

        return $db->select($sql, $params);
    }

    /**
     * Dispatch event on NetFusion Event Bus.
     */
    protected function dispatchEvent(
        string $gateway,
        string $eventType,
        ?string $eventId,
        array $parsedPayload,
        string $rawPayload,
        int $recordId
    ): void {
        if ($this->app->bound(Dispatcher::class)) {
            $dispatcher = $this->app->make(Dispatcher::class);
            $eventData = [
                'id'          => $recordId,
                'gateway'     => $gateway,
                'event_type'  => $eventType,
                'event_id'    => $eventId,
                'payload'     => $parsedPayload,
                'raw_payload' => $rawPayload,
            ];

            // 1. Specific event: e.g. "webhook.incoming.stripe.payment_intent.succeeded"
            $dispatcher->dispatch("webhook.incoming.{$gateway}.{$eventType}", $eventData);

            // 2. Gateway wide event: e.g. "webhook.incoming.stripe"
            $dispatcher->dispatch("webhook.incoming.{$gateway}", $eventData);

            // 3. Universal incoming webhook event
            $dispatcher->dispatch("webhook.incoming", $eventData);
        }
    }

    /**
     * Resolve secret for given gateway from registered array, configuration, or environment variables.
     */
    protected function resolveSecret(string $gateway): ?string
    {
        if (isset($this->secrets[$gateway])) {
            return $this->secrets[$gateway];
        }

        // Check environment variable: e.g. STRIPE_WEBHOOK_SECRET
        $envKey = strtoupper($gateway) . '_WEBHOOK_SECRET';
        $envVal = getenv($envKey);
        if ($envVal !== false && $envVal !== '') {
            return (string) $envVal;
        }

        return null;
    }

    /**
     * Extract unique event ID from payload or headers.
     */
    protected function extractEventId(string $gateway, array $payload, array $headers): ?string
    {
        $normalizedHeaders = [];
        foreach ($headers as $k => $v) {
            $normalizedHeaders[strtolower((string) $k)] = is_array($v) ? implode(', ', $v) : (string) $v;
        }

        return match ($gateway) {
            'stripe' => $payload['id'] ?? null,
            'github' => $normalizedHeaders['x-github-delivery'] ?? ($payload['id'] ?? null),
            'shopify' => $normalizedHeaders['x-shopify-webhook-id'] ?? ($payload['id'] ?? null),
            default => $payload['event_id'] ?? ($payload['id'] ?? null),
        };
    }

    /**
     * Extract event type identifier from payload or headers.
     */
    protected function extractEventType(string $gateway, array $payload, array $headers): string
    {
        $normalizedHeaders = [];
        foreach ($headers as $k => $v) {
            $normalizedHeaders[strtolower((string) $k)] = is_array($v) ? implode(', ', $v) : (string) $v;
        }

        return match ($gateway) {
            'stripe' => (string) ($payload['type'] ?? 'stripe.event'),
            'github' => (string) ($normalizedHeaders['x-github-event'] ?? ($payload['action'] ?? 'github.event')),
            'shopify' => (string) ($normalizedHeaders['x-shopify-topic'] ?? 'shopify.event'),
            default => (string) ($payload['event_type'] ?? ($payload['event'] ?? ($payload['type'] ?? "{$gateway}.event"))),
        };
    }
}
