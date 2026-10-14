<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Sockets/Handshake.php
| Module    : Sockets
| Namespace : NetFusion\Core\Sockets
| Purpose   : RFC 6455 WebSocket HTTP handshake validator and response generator.
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

namespace NetFusion\Core\Sockets;

/**
 * Class Handshake
 *
 * Validates incoming HTTP upgrade requests and generates standard 101 Switching Protocols response.
 *
 * @package NetFusion\Core\Sockets
 */
class Handshake
{
    public const GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

    /**
     * Parsed request method.
     */
    protected string $method = '';

    /**
     * Parsed URI / path.
     */
    protected string $path = '/';

    /**
     * Parsed query parameters.
     *
     * @var array<string, string>
     */
    protected array $queryParams = [];

    /**
     * Parsed headers (normalized lowercase keys).
     *
     * @var array<string, string>
     */
    protected array $headers = [];

    /**
     * Whether request is a valid WebSocket handshake.
     */
    protected bool $isValid = false;

    /**
     * Validation error message if invalid.
     */
    protected string $errorMessage = '';

    public function __construct(string $rawHttpRequest)
    {
        $this->parse($rawHttpRequest);
    }

    /**
     * Parse raw HTTP request string.
     */
    protected function parse(string $rawHttpRequest): void
    {
        $lines = explode("\r\n", trim($rawHttpRequest));
        if (empty($lines) || count($lines) < 1) {
            $this->isValid = false;
            $this->errorMessage = 'Empty HTTP request buffer.';
            return;
        }

        $requestLine = array_shift($lines);
        $parts = explode(' ', $requestLine);
        if (count($parts) < 3) {
            $this->isValid = false;
            $this->errorMessage = 'Invalid HTTP request line.';
            return;
        }

        $this->method = strtoupper($parts[0]);
        $rawUri = $parts[1];

        $uriParts = parse_url($rawUri);
        $this->path = $uriParts['path'] ?? '/';
        if (!empty($uriParts['query'])) {
            parse_str($uriParts['query'], $this->queryParams);
        }

        foreach ($lines as $line) {
            $colonPos = strpos($line, ':');
            if ($colonPos !== false) {
                $name = strtolower(trim(substr($line, 0, $colonPos)));
                $value = trim(substr($line, $colonPos + 1));
                $this->headers[$name] = $value;
            }
        }

        $this->validate();
    }

    /**
     * Validate RFC 6455 handshake requirements.
     */
    protected function validate(): void
    {
        if ($this->method !== 'GET') {
            $this->isValid = false;
            $this->errorMessage = 'WebSocket handshake requires HTTP GET method.';
            return;
        }

        $upgrade = strtolower($this->headers['upgrade'] ?? '');
        if ($upgrade !== 'websocket') {
            $this->isValid = false;
            $this->errorMessage = 'Missing or invalid Upgrade: websocket header.';
            return;
        }

        $connection = strtolower($this->headers['connection'] ?? '');
        if (!str_contains($connection, 'upgrade')) {
            $this->isValid = false;
            $this->errorMessage = 'Missing or invalid Connection: Upgrade header.';
            return;
        }

        $key = $this->headers['sec-websocket-key'] ?? '';
        if (empty($key)) {
            $this->isValid = false;
            $this->errorMessage = 'Missing Sec-WebSocket-Key header.';
            return;
        }

        $version = (int) ($this->headers['sec-websocket-version'] ?? 0);
        if ($version !== 13) {
            $this->isValid = false;
            $this->errorMessage = 'Unsupported Sec-WebSocket-Version (only version 13 is supported).';
            return;
        }

        $this->isValid = true;
    }

    /**
     * Check if handshake is valid.
     */
    public function isValid(): bool
    {
        return $this->isValid;
    }

    /**
     * Get error message if invalid.
     */
    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    /**
     * Get parsed request path.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get parsed query params.
     *
     * @return array<string, string>
     */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /**
     * Get all headers.
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get specific header.
     */
    public function getHeader(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    /**
     * Compute RFC 6455 Sec-WebSocket-Accept token.
     */
    public static function computeAcceptKey(string $secWebSocketKey): string
    {
        return base64_encode(sha1(trim($secWebSocketKey) . self::GUID, true));
    }

    /**
     * Generate HTTP 101 Switching Protocols response.
     *
     * @param array<string, string> $extraHeaders
     */
    public function getResponse(array $extraHeaders = []): string
    {
        if (!$this->isValid) {
            return "HTTP/1.1 400 Bad Request\r\nContent-Type: text/plain\r\nConnection: close\r\n\r\n" . $this->errorMessage;
        }

        $key = $this->headers['sec-websocket-key'] ?? '';
        $accept = self::computeAcceptKey($key);

        $responseHeaders = [
            'Upgrade'              => 'websocket',
            'Connection'           => 'Upgrade',
            'Sec-WebSocket-Accept' => $accept,
        ];

        // Protocol subprotocol negotiation
        if (isset($this->headers['sec-websocket-protocol'])) {
            $protocols = array_map('trim', explode(',', $this->headers['sec-websocket-protocol']));
            if (!empty($protocols[0])) {
                $responseHeaders['Sec-WebSocket-Protocol'] = $protocols[0];
            }
        }

        foreach ($extraHeaders as $k => $v) {
            $responseHeaders[$k] = $v;
        }

        $response = "HTTP/1.1 101 Switching Protocols\r\n";
        foreach ($responseHeaders as $k => $v) {
            $response .= "{$k}: {$v}\r\n";
        }
        $response .= "\r\n";

        return $response;
    }
}
