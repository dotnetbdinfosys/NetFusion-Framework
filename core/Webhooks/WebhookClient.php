<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Webhooks/WebhookClient.php
| Module    : Core\Webhooks
| Namespace : NetFusion\Core\Webhooks
| Purpose   : Pure PHP cURL HTTP client executing secure webhook POST deliveries.
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
 * Class WebhookClient
 *
 * Dispatches HTTP POST requests to third-party endpoints with signatures and latency telemetry.
 *
 * @package NetFusion\Core\Webhooks
 */
class WebhookClient
{
    public function __construct(
        protected WebhookSignerInterface $signer
    ) {}

    /**
     * Send a signed webhook payload to the destination URL.
     *
     * @param string $url
     * @param WebhookPayload $payload
     * @param string $secret
     * @param array<string, string> $extraHeaders
     * @param int $timeoutSeconds
     * @return array{status: int, response: string, duration_ms: float, error: ?string}
     */
    public function send(
        string $url,
        WebhookPayload $payload,
        string $secret,
        array $extraHeaders = [],
        int $timeoutSeconds = 10
    ): array {
        $json = $payload->toJson();
        $signature = $this->signer->sign($json, $secret, $payload->getTimestamp());

        $headers = array_merge([
            'Content-Type'          => 'application/json',
            'User-Agent'            => 'NetFusion-Webhooks/1.0 (+https://www.dotnetbd.com)',
            'X-NetFusion-Event'     => $payload->getEvent(),
            'X-NetFusion-Delivery'  => $payload->getId(),
            'X-NetFusion-Signature' => $signature,
            'X-NetFusion-Attempt'   => (string) $payload->getAttempt(),
        ], $extraHeaders);

        $formattedHeaders = [];
        foreach ($headers as $k => $v) {
            $formattedHeaders[] = "{$k}: {$v}";
        }

        $start = microtime(true);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $formattedHeaders);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutSeconds);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);

        $response = curl_exec($ch);
        $durationMs = (microtime(true) - $start) * 1000;

        $error = null;
        $status = 0;

        if ($response === false) {
            $error = curl_error($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $response = '';
        } else {
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        }

        curl_close($ch);

        return [
            'status'      => $status,
            'response'    => (string) $response,
            'duration_ms' => round($durationMs, 2),
            'error'       => $error,
        ];
    }
}
