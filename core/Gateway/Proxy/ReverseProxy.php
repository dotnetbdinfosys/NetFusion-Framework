<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Gateway\Proxy;

use NetFusion\Core\Gateway\Contracts\UpstreamInterface;
use NetFusion\Core\Gateway\Models\GatewayRequest;
use NetFusion\Core\Gateway\Models\GatewayResponse;
use NetFusion\Core\Gateway\Models\Upstream;

/**
 * Fault-Tolerant Reverse Proxy using pure PHP streams.
 *
 * Features:
 *  - No cURL / no external dependencies
 *  - Streaming response body (chunked reads)
 *  - Connection timeout & response timeout
 *  - Automatic HTTP/1.1 chunked transfer decoding
 *  - Simulation mode for unit tests
 */
class ReverseProxy
{
    public function __construct(
        private readonly int $connectTimeoutSeconds = 5,
        private readonly int $readTimeoutSeconds = 30,
        private readonly bool $simulate = false,
        private readonly ?GatewayResponse $simulatedResponse = null
    ) {}

    /**
     * Forwards the gateway request to the selected upstream and returns
     * a GatewayResponse with the upstream's status, headers, and body.
     */
    public function forward(GatewayRequest $request, UpstreamInterface $upstream): GatewayResponse
    {
        if ($this->simulate) {
            return $this->simulatedResponse ?? GatewayResponse::json(200, ['proxied' => true, 'upstream' => $upstream->getId()]);
        }

        $startTime = microtime(true);

        $host   = $upstream->getHost();
        $port   = $upstream->getPort();
        $scheme = $upstream->getScheme();
        $prefix = $scheme === 'https' ? 'ssl://' : '';

        $errno  = 0;
        $errstr = '';
        $socket = @fsockopen($prefix . $host, $port, $errno, $errstr, $this->connectTimeoutSeconds);

        if (!$socket) {
            $latency = (microtime(true) - $startTime) * 1000;
            return new GatewayResponse(
                statusCode: 502,
                body: json_encode(['error' => 'Bad Gateway', 'detail' => "Cannot connect to upstream: {$errstr}"]),
                headers: ['content-type' => ['application/json']],
                latencyMs: $latency,
                upstreamId: $upstream->getId()
            );
        }

        // Apply read timeout
        stream_set_timeout($socket, $this->readTimeoutSeconds);

        // Build upstream request
        $path    = $request->getUri() ?: '/';
        $method  = $request->getMethod();
        $body    = $request->getBody();
        $headers = $request->getHeaders();

        $headers['host']           = $host . ':' . $port;
        $headers['connection']     = 'close';
        $headers['content-length'] = (string)strlen($body);

        $rawRequest = "{$method} {$path} HTTP/1.1\r\n";
        foreach ($headers as $name => $value) {
            $rawRequest .= "{$name}: {$value}\r\n";
        }
        $rawRequest .= "\r\n{$body}";

        fwrite($socket, $rawRequest);

        // Read full response
        $rawResponse = '';
        while (!feof($socket)) {
            $chunk = fread($socket, 8192);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $rawResponse .= $chunk;
        }
        fclose($socket);

        $latency = (microtime(true) - $startTime) * 1000;

        return $this->parseResponse($rawResponse, $latency, $upstream->getId());
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Parses a raw HTTP/1.x response string into a GatewayResponse.
     */
    private function parseResponse(string $raw, float $latencyMs, string $upstreamId): GatewayResponse
    {
        if ($raw === '') {
            return new GatewayResponse(502, '{"error":"Empty upstream response"}',
                ['content-type' => ['application/json']], $latencyMs, $upstreamId);
        }

        // Split head and body
        $parts = explode("\r\n\r\n", $raw, 2);
        $head  = $parts[0] ?? '';
        $body  = $parts[1] ?? '';

        // Parse status line
        $lines      = explode("\r\n", $head);
        $statusLine = array_shift($lines);
        $statusCode = 200;

        if (preg_match('/^HTTP\/\d\.\d (\d{3})/', $statusLine, $m)) {
            $statusCode = (int)$m[1];
        }

        // Parse headers
        $headers = [];
        foreach ($lines as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($name))] = [trim($value)];
            }
        }

        // Decode chunked transfer encoding
        if (isset($headers['transfer-encoding']) && str_contains($headers['transfer-encoding'][0] ?? '', 'chunked')) {
            $body = $this->decodeChunked($body);
            unset($headers['transfer-encoding']);
        }

        return new GatewayResponse(
            statusCode: $statusCode,
            body: $body,
            headers: $headers,
            latencyMs: $latencyMs,
            upstreamId: $upstreamId
        );
    }

    /**
     * Decodes HTTP/1.1 chunked transfer-encoded body.
     */
    private function decodeChunked(string $body): string
    {
        $decoded = '';
        $pos     = 0;
        $len     = strlen($body);

        while ($pos < $len) {
            // Read chunk size line
            $eol      = strpos($body, "\r\n", $pos);
            if ($eol === false) {
                break;
            }
            $sizeHex  = trim(substr($body, $pos, $eol - $pos));
            $chunkSize = hexdec($sizeHex);
            $pos       = $eol + 2;

            if ($chunkSize === 0) {
                break;
            }

            $decoded .= substr($body, $pos, $chunkSize);
            $pos     += $chunkSize + 2; // skip trailing \r\n
        }

        return $decoded;
    }
}
