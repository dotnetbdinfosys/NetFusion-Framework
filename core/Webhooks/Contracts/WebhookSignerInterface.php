<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Webhooks/Contracts/WebhookSignerInterface.php
| Module    : Core\Webhooks
| Namespace : NetFusion\Core\Webhooks\Contracts
| Purpose   : Interface for cryptographic signing and verification of outgoing webhooks.
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

namespace NetFusion\Core\Webhooks\Contracts;

/**
 * Interface WebhookSignerInterface
 *
 * Provides cryptographic signature generation and verification for webhook delivery.
 *
 * @package NetFusion\Core\Webhooks\Contracts
 */
interface WebhookSignerInterface
{
    /**
     * Generate the signature string for the webhook payload.
     *
     * @param string $payload Serialized JSON payload.
     * @param string $secret Shared cryptographic secret.
     * @param int|null $timestamp Unix timestamp.
     * @return string Formatted signature header (e.g., "t=...,v1=...").
     */
    public function sign(string $payload, string $secret, ?int $timestamp = null): string;

    /**
     * Verify a received signature against the payload and secret.
     *
     * @param string $signatureHeader The header value (e.g., "t=...,v1=...").
     * @param string $payload Serialized JSON payload.
     * @param string $secret Shared cryptographic secret.
     * @param int $toleranceSeconds Maximum allowed clock skew in seconds (0 to disable).
     * @return bool
     */
    public function verify(string $signatureHeader, string $payload, string $secret, int $toleranceSeconds = 300): bool;
}
