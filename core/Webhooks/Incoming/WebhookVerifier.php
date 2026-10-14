<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Webhooks/Incoming/WebhookVerifier.php
| Module    : Webhooks
| Namespace : NetFusion\Core\Webhooks\Incoming
| Purpose   : Pure PHP multi-gateway incoming webhook signature verification.
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

use NetFusion\Core\Webhooks\Contracts\IncomingWebhookVerifierInterface;

/**
 * Class WebhookVerifier
 *
 * Verifies authenticity and freshness of incoming webhooks across
 * multiple standard providers (Stripe, GitHub, Shopify, NetFusion, generic).
 *
 * @package NetFusion\Core\Webhooks\Incoming
 */
class WebhookVerifier implements IncomingWebhookVerifierInterface
{
    /**
     * Custom verifier callables indexed by gateway name.
     *
     * @var array<string, callable>
     */
    protected array $customVerifiers = [];

    /**
     * Register a custom verifier callable for a specific gateway.
     *
     * @param string $gateway
     * @param callable $callback fn(string $payload, array $headers, string $secret, int $tolerance): bool
     */
    public function extend(string $gateway, callable $callback): void
    {
        $this->customVerifiers[strtolower($gateway)] = $callback;
    }

    /**
     * {@inheritdoc}
     */
    public function verify(
        string $gateway,
        string $payload,
        array $headers,
        string $secret,
        int $toleranceSeconds = 300
    ): bool {
        $gatewayKey = strtolower(trim($gateway));

        // If a custom handler is registered, delegate to it
        if (isset($this->customVerifiers[$gatewayKey])) {
            return (bool) call_user_func(
                $this->customVerifiers[$gatewayKey],
                $payload,
                $headers,
                $secret,
                $toleranceSeconds
            );
        }

        $normalizedHeaders = $this->normalizeHeaders($headers);

        return match ($gatewayKey) {
            'stripe' => $this->verifyStripe($payload, $normalizedHeaders, $secret, $toleranceSeconds),
            'github' => $this->verifyGithub($payload, $normalizedHeaders, $secret),
            'shopify' => $this->verifyShopify($payload, $normalizedHeaders, $secret),
            default => $this->verifyGeneric($payload, $normalizedHeaders, $secret, $toleranceSeconds),
        };
    }

    /**
     * Verify Stripe signature: Stripe-Signature: t=1492774577,v1=5257a869e7eee...
     */
    protected function verifyStripe(string $payload, array $headers, string $secret, int $tolerance): bool
    {
        $sigHeader = $headers['stripe-signature'] ?? $headers['x-signature'] ?? null;
        if (empty($sigHeader)) {
            return false;
        }

        return $this->verifyTimestampedHmac($sigHeader, $payload, $secret, $tolerance);
    }

    /**
     * Verify GitHub signature: X-Hub-Signature-256: sha256=...
     */
    protected function verifyGithub(string $payload, array $headers, string $secret): bool
    {
        $sigHeader = $headers['x-hub-signature-256'] ?? $headers['x-hub-signature'] ?? null;
        if (empty($sigHeader)) {
            return false;
        }

        $expectedSig = hash_hmac('sha256', $payload, $secret);
        $cleanSig = str_starts_with($sigHeader, 'sha256=')
            ? substr($sigHeader, 7)
            : $sigHeader;

        return hash_equals($expectedSig, $cleanSig);
    }

    /**
     * Verify Shopify signature: X-Shopify-Hmac-Sha256: base64(hmac_sha256)
     */
    protected function verifyShopify(string $payload, array $headers, string $secret): bool
    {
        $sigHeader = $headers['x-shopify-hmac-sha256'] ?? null;
        if (empty($sigHeader)) {
            return false;
        }

        $expectedSig = base64_encode(hash_hmac('sha256', $payload, $secret, true));
        return hash_equals($expectedSig, $sigHeader);
    }

    /**
     * Verify Generic / NetFusion webhook signatures.
     */
    protected function verifyGeneric(string $payload, array $headers, string $secret, int $tolerance): bool
    {
        $sigHeader = $headers['x-signature']
            ?? $headers['x-netfusion-signature']
            ?? $headers['signature']
            ?? $headers['x-hub-signature-256']
            ?? null;

        if (empty($sigHeader)) {
            return false;
        }

        // If formatted as t=...,v1=...
        if (str_contains($sigHeader, 't=') && str_contains($sigHeader, 'v1=')) {
            return $this->verifyTimestampedHmac($sigHeader, $payload, $secret, $tolerance);
        }

        // If formatted as sha256=...
        if (str_starts_with($sigHeader, 'sha256=')) {
            $expected = hash_hmac('sha256', $payload, $secret);
            return hash_equals($expected, substr($sigHeader, 7));
        }

        // Direct raw HMAC-SHA256 hex
        $expected = hash_hmac('sha256', $payload, $secret);
        return hash_equals($expected, $sigHeader);
    }

    /**
     * Verify timestamped HMAC-SHA256 signature with replay tolerance.
     */
    protected function verifyTimestampedHmac(string $header, string $payload, string $secret, int $tolerance): bool
    {
        $timestamp = null;
        $signature = null;

        $parts = explode(',', $header);
        foreach ($parts as $part) {
            $kv = explode('=', trim($part), 2);
            if (count($kv) === 2) {
                if ($kv[0] === 't') {
                    $timestamp = (int) $kv[1];
                } elseif ($kv[0] === 'v1') {
                    $signature = $kv[1];
                }
            }
        }

        if ($timestamp === null || $signature === null) {
            return false;
        }

        if ($tolerance > 0 && abs(time() - $timestamp) > $tolerance) {
            return false;
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);
        return hash_equals($expected, $signature);
    }

    /**
     * Normalize header array keys to lowercase.
     *
     * @param array<string, mixed> $headers
     * @return array<string, string>
     */
    protected function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $key => $value) {
            $val = is_array($value) ? implode(', ', $value) : (string) $value;
            $normalized[strtolower((string) $key)] = $val;
        }
        return $normalized;
    }
}
