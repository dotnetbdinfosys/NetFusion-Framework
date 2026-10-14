<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Http/Response.php
| Module    : Core\Http
| Namespace : NetFusion\Core\Http
| Purpose   : Fluent HTTP response builder encapsulating status codes, headers, cookies, and body content.
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

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Http/Response.php
| Module    : Core\Http
| Namespace : NetFusion\Core\Http
| Purpose   : Fluent HTTP response builder encapsulating status codes, headers, cookies, and body content.
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

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Http/Response.php
| Module    : Core\Http
| Namespace : NetFusion\Core\Http
| Purpose   : Fluent HTTP response builder encapsulating status codes, headers, cookies, and body content.
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

namespace NetFusion\Core\Http;

/**
 * NetFusion HTTP Response
 *
 * A fluent HTTP Response builder that encapsulates:
 *  - HTTP status code and reason phrase
 *  - Response headers (with chained setters)
 *  - Response body content
 *  - Cookie management
 *  - The send() method (outputs headers + body to the client)
 *
 * @package NetFusion\Core\Http
 * @version 1.0.0
 */
class Response
{
    /**
     * HTTP status phrases for common status codes.
     *
     * @var array<int, string>
     */
    protected const STATUS_PHRASES = [
        // 1xx Informational
        100 => 'Continue',
        101 => 'Switching Protocols',
        // 2xx Success
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        204 => 'No Content',
        206 => 'Partial Content',
        // 3xx Redirection
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        // 4xx Client Errors
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        429 => 'Too Many Requests',
        // 5xx Server Errors
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
    ];

    /**
     * The HTTP status code.
     */
    protected int $statusCode;

    /**
     * The response body content.
     */
    protected string $content = '';

    /**
     * The response headers.
     *
     * @var array<string, string|string[]>
     */
    protected array $headers = [];

    /**
     * Cookies to set.
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $cookies = [];

    /**
     * @param string $content    The response body.
     * @param int    $statusCode HTTP status code.
     * @param array<string, string> $headers Additional headers.
     */
    public function __construct(
        string $content    = '',
        int    $statusCode = 200,
        array  $headers    = [],
    ) {
        $this->statusCode = $statusCode;
        $this->content    = $content;

        // Set default headers
        $this->withHeader('Content-Type', 'text/html; charset=UTF-8')
             ->withHeader('X-Powered-By', 'NetFusion Framework');

        foreach ($headers as $name => $value) {
            $this->withHeader($name, $value);
        }
    }

    // =========================================================================
    //  Static Factory Methods
    // =========================================================================

    /**
     * Create a plain text response.
     *
     * @param string $content    The response body.
     * @param int    $statusCode HTTP status code.
     */
    public static function text(string $content, int $statusCode = 200): static
    {
        return new static($content, $statusCode, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * Create an HTML response (default).
     *
     * @param string $content    The HTML body.
     * @param int    $statusCode HTTP status code.
     */
    public static function html(string $content, int $statusCode = 200): static
    {
        return new static($content, $statusCode);
    }

    /**
     * Create a response containing a rendered view.
     *
     * @param  string               $view View name or namespace::view.
     * @param  array<string, mixed> $data View variables.
     * @param  int                  $statusCode HTTP status code.
     * @return static
     */
    public static function view(string $view, array $data = [], int $statusCode = 200): static
    {
        $rendered = app(\NetFusion\Core\View\ViewFactory::class)->render($view, $data);
        return new static($rendered, $statusCode);
    }

    /**
     * Create a JSON response.
     *
     * @param mixed $data       Data to JSON-encode.
     * @param int   $statusCode HTTP status code.
     * @param int   $options    json_encode() options.
     */
    public static function json(
        mixed $data,
        int   $statusCode = 200,
        int   $options    = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    ): JsonResponse {
        return new JsonResponse($data, $statusCode, $options);
    }

    /**
     * Create a redirect response.
     *
     * @param string $url        The URL to redirect to.
     * @param int    $statusCode 301 (permanent) or 302 (temporary).
     */
    public static function redirect(string $url, int $statusCode = 302): RedirectResponse
    {
        return new RedirectResponse($url, $statusCode);
    }

    /**
     * Create a 404 Not Found response.
     *
     * @param string $message Custom message.
     */
    public static function notFound(string $message = 'Not Found'): static
    {
        return new static($message, 404);
    }

    /**
     * Create a 405 Method Not Allowed response.
     *
     * @param string[] $allowedMethods The HTTP methods allowed for this route.
     */
    public static function methodNotAllowed(array $allowedMethods): static
    {
        return (new static('Method Not Allowed', 405))
            ->withHeader('Allow', implode(', ', $allowedMethods));
    }

    /**
     * Create a 500 Internal Server Error response.
     *
     * @param string $message Error message.
     */
    public static function serverError(string $message = 'Internal Server Error'): static
    {
        return new static($message, 500);
    }

    // =========================================================================
    //  Status Code
    // =========================================================================

    /**
     * Set the HTTP status code.
     *
     * @param  int    $code HTTP status code.
     * @return static (fluent)
     */
    public function withStatus(int $code): static
    {
        $clone = clone $this;
        $clone->statusCode = $code;
        return $clone;
    }

    /**
     * Get the HTTP status code.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get the HTTP status reason phrase.
     */
    public function getReasonPhrase(): string
    {
        return static::STATUS_PHRASES[$this->statusCode] ?? 'Unknown';
    }

    /**
     * Check if the response status indicates success (2xx).
     */
    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * Check if the response is a redirect (3xx).
     */
    public function isRedirect(): bool
    {
        return $this->statusCode >= 300 && $this->statusCode < 400;
    }

    /**
     * Check if the response is a client error (4xx).
     */
    public function isClientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    /**
     * Check if the response is a server error (5xx).
     */
    public function isServerError(): bool
    {
        return $this->statusCode >= 500;
    }

    // =========================================================================
    //  Headers
    // =========================================================================

    /**
     * Set (or replace) a response header. Returns a new instance (immutable style).
     *
     * @param  string $name  The header name.
     * @param  string $value The header value.
     * @return static
     */
    public function withHeader(string $name, string $value): static
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function setHeader(string $name, string $value): static
    {
        return $this->withHeader($name, $value);
    }

    /**
     * Add a response header without replacing existing values of the same name.
     *
     * @param  string $name  The header name.
     * @param  string $value The header value.
     * @return static
     */
    public function withAddedHeader(string $name, string $value): static
    {
        if (isset($this->headers[$name])) {
            if (!is_array($this->headers[$name])) {
                $this->headers[$name] = [$this->headers[$name]];
            }
            $this->headers[$name][] = $value;
        } else {
            $this->headers[$name] = $value;
        }
        return $this;
    }

    /**
     * Remove a header.
     *
     * @param  string $name Header name.
     * @return static
     */
    public function withoutHeader(string $name): static
    {
        unset($this->headers[$name]);
        return $this;
    }

    /**
     * Get the value of a single header.
     *
     * @param  string      $name    Header name.
     * @param  string|null $default Default value.
     * @return string|null
     */
    public function getHeader(string $name): ?string
    {
        $value = $this->headers[$name] ?? null;
        if (is_array($value)) {
            return implode(', ', $value);
        }
        return $value;
    }

    /**
     * Get all response headers.
     *
     * @return array<string, string|string[]>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Check if a header exists.
     */
    public function hasHeader(string $name): bool
    {
        return isset($this->headers[$name]);
    }

    // =========================================================================
    //  Content
    // =========================================================================

    /**
     * Set the response body content. Returns a new instance.
     *
     * @param  string $content The response body.
     * @return static
     */
    public function withContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    /**
     * Get the response body content.
     */
    public function getContent(): string
    {
        return $this->content;
    }

    public function getBody(): string
    {
        return $this->getContent();
    }

    // =========================================================================
    //  Cookies
    // =========================================================================

    /**
     * Queue a cookie to be sent with the response.
     *
     * @param string      $name     Cookie name.
     * @param string      $value    Cookie value.
     * @param int         $minutes  Minutes until expiry (0 = session cookie).
     * @param string      $path     Cookie path.
     * @param string      $domain   Cookie domain.
     * @param bool        $secure   HTTPS only.
     * @param bool        $httpOnly Inaccessible to JavaScript.
     * @param string      $sameSite SameSite policy (Strict|Lax|None).
     * @return static
     */
    public function withCookie(
        string $name,
        string $value    = '',
        int    $minutes  = 0,
        string $path     = '/',
        string $domain   = '',
        bool   $secure   = false,
        bool   $httpOnly = true,
        string $sameSite = 'Lax',
    ): static {
        $this->cookies[] = compact(
            'name', 'value', 'minutes', 'path', 'domain', 'secure', 'httpOnly', 'sameSite'
        );
        return $this;
    }

    /**
     * Queue a cookie deletion (expired cookie).
     *
     * @param  string $name   Cookie name.
     * @param  string $path   Cookie path.
     * @param  string $domain Cookie domain.
     * @return static
     */
    public function forgetCookie(string $name, string $path = '/', string $domain = ''): static
    {
        return $this->withCookie($name, '', -2628000, $path, $domain);
    }

    // =========================================================================
    //  Send
    // =========================================================================

    /**
     * Send the HTTP response to the client.
     * Outputs the status line, headers, cookies, and body.
     */
    public function send(): void
    {
        $this->sendHeaders();
        $this->sendCookies();
        $this->sendContent();
    }

    /**
     * Send all response headers.
     */
    protected function sendHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        // Status line
        $phrase = $this->getReasonPhrase();
        header(
            sprintf('HTTP/1.1 %d %s', $this->statusCode, $phrase),
            replace: true,
            response_code: $this->statusCode
        );

        // All registered headers
        foreach ($this->headers as $name => $value) {
            if (is_array($value)) {
                foreach ($value as $v) {
                    header("{$name}: {$v}", replace: false);
                }
            } else {
                header("{$name}: {$value}", replace: true);
            }
        }
    }

    /**
     * Send all queued cookies.
     */
    protected function sendCookies(): void
    {
        foreach ($this->cookies as $cookie) {
            setcookie(
                name    : $cookie['name'],
                value   : $cookie['value'],
                expires_or_options: [
                    'expires'  => $cookie['minutes'] > 0 ? time() + ($cookie['minutes'] * 60) : 0,
                    'path'     => $cookie['path'],
                    'domain'   => $cookie['domain'],
                    'secure'   => $cookie['secure'],
                    'httponly' => $cookie['httpOnly'],
                    'samesite' => $cookie['sameSite'],
                ],
            );
        }
    }

    /**
     * Output the response body.
     */
    protected function sendContent(): void
    {
        echo $this->content;
    }
}
