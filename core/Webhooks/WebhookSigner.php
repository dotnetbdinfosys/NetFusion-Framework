<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Webhooks/WebhookSigner.php
| Module    : Core\Webhooks
| Namespace : NetFusion\Core\Webhooks
| Purpose   : Pure PHP HMAC-SHA256 webhook signature generator and validator.
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

use NetFusion\Core\Webhooks\Contracts\WebhookSignerInterface;

/**
 * Class WebhookSigner
 *
 * Implements industry-standard HMAC-SHA256 timestamped signatures
 * preventing replay attacks and tampering.
 *
 * @package NetFusion\Core\Webhooks
 */
class WebhookSigner implements WebhookSignerInterface
{
    /**
     * Generate standard timestamped HMAC-SHA256 signature.
     */
    public function sign(string $payload, string $secret, ?int $timestamp = null): string
    {
        $timestamp = $timestamp ?? time();
        $signedPayload = "{$timestamp}.{$payload}";
        $hash = hash_hmac('sha256', $signedPayload, $secret);

        return "t={$timestamp},v1={$hash}";
    }

    /**
     * Verify timestamped signature with replay protection tolerance.
     */
    public function verify(string $signatureHeader, string $payload, string $secret, int $toleranceSeconds = 300): bool
    {
        $timestamp = null;
        $signature = null;

        $parts = explode(',', $signatureHeader);
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

        if ($toleranceSeconds > 0 && abs(time() - $timestamp) > $toleranceSeconds) {
            return false;
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        return hash_equals($expected, $signature);
    }
}
