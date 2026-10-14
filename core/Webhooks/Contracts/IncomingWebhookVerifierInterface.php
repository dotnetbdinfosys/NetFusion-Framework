<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Webhooks/Contracts/IncomingWebhookVerifierInterface.php
| Module    : Webhooks
| Namespace : NetFusion\Core\Webhooks\Contracts
| Purpose   : Interface for verifying authenticity and integrity of incoming webhook payloads.
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
 * Interface IncomingWebhookVerifierInterface
 *
 * Provides cryptographic verification for incoming third-party webhooks.
 *
 * @package NetFusion\Core\Webhooks\Contracts
 */
interface IncomingWebhookVerifierInterface
{
    /**
     * Verify incoming webhook request signature against payload and gateway secret.
     *
     * @param string $gateway Gateway provider key (e.g., 'stripe', 'github', 'netfusion', 'custom').
     * @param string $payload Raw HTTP request body.
     * @param array<string, mixed> $headers HTTP request headers.
     * @param string $secret Shared secret key.
     * @param int $toleranceSeconds Allowed timestamp skew in seconds.
     * @return bool True if valid, false otherwise.
     */
    public function verify(string $gateway, string $payload, array $headers, string $secret, int $toleranceSeconds = 300): bool;
}
