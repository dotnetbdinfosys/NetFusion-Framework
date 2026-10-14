<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Http/Request.php
| Module    : Core\Http
| Namespace : NetFusion\Core\Http
| Purpose   : Immutable HTTP request abstraction capturing superglobals, headers, cookies, and route parameters.
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
| File      : core/Http/Request.php
| Module    : Core\Http
| Namespace : NetFusion\Core\Http
| Purpose   : Immutable HTTP request abstraction capturing superglobals, headers, cookies, and route parameters.
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
| File      : core/Http/Request.php
| Module    : Core\Http
| Namespace : NetFusion\Core\Http
| Purpose   : Immutable HTTP request abstraction capturing superglobals, headers, cookies, and route parameters.
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
 * NetFusion HTTP Request
 *
 * Wraps PHP's superglobals ($_GET, $_POST, $_SERVER, $_FILES, $_COOKIE)
 * into a clean, immutable, testable object. Provides rich helpers for:
 *
 *  - Accessing query params, form data, JSON body, headers, cookies
 *  - Input validation / type-casting helpers (string, int, bool, array)
 *  - IP detection (including proxy-aware X-Forwarded-For)
 *  - Content-Type / Accept negotiation
 *  - Route parameter injection (set by the Router after matching)
 *  - Ajax / JSON / API request detection
 *
 * @package NetFusion\Core\Http
 * @version 1.0.0
 */
class Request
{
    /**
     * The HTTP method (GET, POST, PUT, PATCH, DELETE, OPTIONS, HEAD).
     */
    private readonly string $method;

    /**
     * The raw URI path (without query string).
     */
    private readonly string $path;

    /**
     * The full request URI including query string.
     */
    private readonly string $uri;

    /**
     * Query string parameters ($_GET).
     *
     * @var array<string, mixed>
     */
    private readonly array $query;

    /**
     * POST body parameters ($_POST).
     *
     * @var array<string, mixed>
     */
    private readonly array $post;

    /**
     * Parsed JSON body (when Content-Type: application/json).
     *
     * @var array<string, mixed>|null
     */
    private ?array $jsonBody = null;

    /**
     * Server parameters ($_SERVER).
     *
     * @var array<string, mixed>
     */
    private readonly array $server;

    /**
     * Cookie parameters ($_COOKIE).
     *
     * @var array<string, mixed>
     */
    private readonly array $cookies;

    /**
     * Uploaded file parameters ($_FILES).
     *
     * @var array<string, mixed>
     */
    private readonly array $files;

    /**
     * Extracted route parameters (e.g., {id}, {slug}) set by Router.
     *
     * @var array<string, string>
     */
    private array $routeParams = [];

    /**
     * Arbitrary request attributes set by middleware (e.g., tenant, user).
     *
     * @var array<string, mixed>
     */
    private array $attributes = [];

    /**
     * Raw body content.
     */
    private ?string $rawBody = null;

    /**
     * Construct a Request from raw PHP data.
     *
     * @param string              $method  The HTTP method (uppercase).
     * @param string              $uri     The full request URI.
     * @param array<string,mixed> $query   Query string params.
     * @param array<string,mixed> $post    POST body params.
     * @param array<string,mixed> $server  Server/environment params.
     * @param array<string,mixed> $cookies Cookie params.
     * @param array<string,mixed> $files   Uploaded files.
     * @param string|null         $content Raw body content.
     */
    public function __construct(
        string $method,
        string $uri,
        array  $query   = [],
        array  $post    = [],
        array  $server  = [],
        array  $cookies = [],
        array  $files   = [],
        ?string $content = null,
    ) {
        $this->rawBody = $content;
        // Method override: support _method field (for HTML forms)
        $method = strtoupper($method);
        if ($method === 'POST') {
            $override = strtoupper((string) ($post['_method'] ?? $server['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? ''));
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = $override;
            }
        }

        $this->method  = $method;
        $this->uri     = $uri;
        $this->path    = parse_url($uri, PHP_URL_PATH) ?? '/';
        $this->query   = $query;
        $this->post    = $post;
        $this->server  = $server;
        $this->cookies = $cookies;
        $this->files   = $files;
    }

    // =========================================================================
    //  Static Factory — Capture Current HTTP Request
    // =========================================================================

    /**
     * Capture the current HTTP request from PHP superglobals.
     * This is the primary factory method used by the HTTP Kernel.
     *
     * @return static
     */
    public static function capture(): static
    {
        return new static(
            method : $_SERVER['REQUEST_METHOD'] ?? 'GET',
            uri    : static::resolveUri(),
            query  : $_GET,
            post   : $_POST,
            server : $_SERVER,
            cookies: $_COOKIE,
            files  : $_FILES,
        );
    }

    /**
     * Create a Request instance for testing purposes.
     *
     * @param  string              $method  HTTP method.
     * @param  string              $uri     Request URI.
     * @param  array<string,mixed> $params  GET or POST params.
     * @param  array<string,mixed> $server  Optional server overrides.
     * @return static
     */
    public static function create(
        string $method = 'GET',
        string $uri    = '/',
        array  $params = [],
        array  $server = [],
        ?string $content = null,
    ): static {
        $method = strtoupper($method);

        // Parse query string from URI if present
        $parsedUri   = parse_url($uri);
        $uriQuery    = [];
        if (isset($parsedUri['query'])) {
            parse_str($parsedUri['query'], $uriQuery);
        }

        $query = in_array($method, ['GET', 'HEAD'], true)
            ? array_merge($uriQuery, $params)
            : $uriQuery;

        $post = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) ? $params : [];

        $defaultServer = [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI'    => $uri,
            'HTTP_HOST'      => 'localhost',
            'SERVER_NAME'    => 'localhost',
            'SERVER_PORT'    => '80',
            'REMOTE_ADDR'    => '127.0.0.1',
            'HTTP_USER_AGENT'=> 'NetFusion/Test',
        ];

        return new static(
            method : $method,
            uri    : $uri,
            query  : $query,
            post   : $post,
            server : array_merge($defaultServer, $server),
            cookies: [],
            files  : [],
            content: $content,
        );
    }

    /**
     * Resolve the full request URI from $_SERVER.
     */
    private static function resolveUri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        // Strip the script name prefix if running without mod_rewrite
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $scriptDir  = dirname($scriptName);

        if ($scriptDir !== '/' && str_starts_with($uri, $scriptDir)) {
            $uri = substr($uri, strlen($scriptDir));
        }

        return $uri ?: '/';
    }

    // =========================================================================
    //  Method & URI Accessors
    // =========================================================================

    /**
     * Get the HTTP method (always uppercase: GET, POST, PUT, PATCH, DELETE, etc.)
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * Check if the request method matches the given method(s).
     *
     * @param string ...$methods Methods to check (case-insensitive).
     */
    public function isMethod(string ...$methods): bool
    {
        foreach ($methods as $method) {
            if (strtoupper($method) === $this->method) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get the request path (without query string).
     * e.g., "/users/42/edit"
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * Get the full request URI (with query string).
     * e.g., "/users?page=2&sort=asc"
     */
    public function uri(): string
    {
        return $this->uri;
    }

    /**
     * Get the full URL including scheme and host.
     * e.g., "https://app.example.com/users/42"
     */
    public function url(): string
    {
        $scheme = $this->isSecure() ? 'https' : 'http';
        return $scheme . '://' . $this->host() . $this->path;
    }

    /**
     * Get the full URL with query string.
     */
    public function fullUrl(): string
    {
        $scheme = $this->isSecure() ? 'https' : 'http';
        return $scheme . '://' . $this->host() . $this->uri;
    }

    /**
     * Get the HTTP host (hostname:port if non-standard).
     */
    public function host(): string
    {
        return $this->server['HTTP_HOST'] ?? ($this->server['SERVER_NAME'] ?? 'localhost');
    }

    /**
     * Get only the hostname (without port).
     */
    public function hostname(): string
    {
        return explode(':', $this->host())[0];
    }

    /**
     * Get the subdomain from the host (e.g., 'acme' from 'acme.app.com').
     *
     * @param  int $offset  Number of top-level domain parts (default: 2 for .com, .org, etc.)
     * @return string|null
     */
    public function subdomain(int $offset = 2): ?string
    {
        $parts = explode('.', $this->hostname());
        if (count($parts) <= $offset) {
            return null;
        }
        return implode('.', array_slice($parts, 0, count($parts) - $offset));
    }

    /**
     * Get the port number from the server.
     */
    public function port(): int
    {
        return (int) ($this->server['SERVER_PORT'] ?? 80);
    }

    /**
     * Determine if the request was made over HTTPS.
     */
    public function isSecure(): bool
    {
        return (
            ($this->server['HTTPS'] ?? '') === 'on'
            || ($this->server['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
            || ($this->server['SERVER_PORT'] ?? '') === '443'
        );
    }

    // =========================================================================
    //  Input: Query, POST, JSON Body
    // =========================================================================

    /**
     * Get an input value from POST, JSON body, or GET (in that priority order).
     * Returns $default if the key does not exist.
     *
     * @param  string|null $key     The input key, or null to get all inputs.
     * @param  mixed       $default Default value.
     * @return mixed
     */
    public function input(string $key = null, mixed $default = null): mixed
    {
        $all = array_merge($this->query, $this->post, $this->json() ?? []);

        if ($key === null) {
            return $all;
        }

        return $this->dotGet($all, $key, $default);
    }

    /**
     * Get a query string (?param=value) parameter.
     *
     * @param string|null $key     The query key, or null for all.
     * @param mixed       $default Default value.
     */
    public function query(string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }
        return $this->dotGet($this->query, $key, $default);
    }

    /**
     * Get a POST body parameter.
     *
     * @param string|null $key     The POST key, or null for all.
     * @param mixed       $default Default value.
     */
    public function post(string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->post;
        }
        return $this->dotGet($this->post, $key, $default);
    }

    /**
     * Get the raw request body content.
     */
    public function getContent(): string
    {
        if ($this->rawBody !== null) {
            return $this->rawBody;
        }

        $raw = file_get_contents('php://input');
        return $raw !== false ? $raw : '';
    }

    /**
     * Set the raw request body content.
     */
    public function setContent(string $content): static
    {
        $this->rawBody = $content;
        $this->jsonBody = null; // Clear cached json
        return $this;
    }

    /**
     * Get the parsed JSON request body as an array.
     * Returns null if the body is not valid JSON or Content-Type is not JSON.
     *
     * @param  string|null $key     Optional key to extract from the JSON body.
     * @param  mixed       $default Default value.
     * @return array<string,mixed>|mixed|null
     */
    public function json(string $key = null, mixed $default = null): mixed
    {
        if ($this->jsonBody === null) {
            $raw = $this->getContent();
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                $this->jsonBody = is_array($decoded) ? $decoded : [];
            } else {
                $this->jsonBody = [];
            }
        }

        if ($key === null) {
            return $this->jsonBody;
        }

        return $this->dotGet($this->jsonBody ?? [], $key, $default);
    }

    /**
     * Get all input (merged query + post + json), optionally filtered by keys.
     *
     * @param  string[] $keys Keys to include. Empty = all.
     * @return array<string, mixed>
     */
    public function all(array $keys = []): array
    {
        $all = array_merge($this->query, $this->post, $this->json() ?? []);
        if (empty($keys)) {
            return $all;
        }
        return array_intersect_key($all, array_flip($keys));
    }

    /**
     * Get only the specified keys from the input.
     *
     * @param  string ...$keys The keys to extract.
     * @return array<string, mixed>
     */
    public function only(string ...$keys): array
    {
        return $this->all($keys);
    }

    /**
     * Get all input except the specified keys.
     *
     * @param  string ...$keys Keys to exclude.
     * @return array<string, mixed>
     */
    public function except(string ...$keys): array
    {
        return array_diff_key($this->all(), array_flip($keys));
    }

    /**
     * Check if an input key exists (even if null or empty string).
     */
    public function has(string $key): bool
    {
        $all = $this->all();
        return array_key_exists($key, $all);
    }

    /**
     * Check if an input key exists and is not empty.
     */
    public function filled(string $key): bool
    {
        $value = $this->input($key);
        return $value !== null && $value !== '' && $value !== [];
    }

    // =========================================================================
    //  Typed Input Accessors
    // =========================================================================

    /**
     * Get an input value as a string.
     */
    public function string(string $key, string $default = ''): string
    {
        return (string) ($this->input($key) ?? $default);
    }

    /**
     * Get an input value as an integer.
     */
    public function int(string $key, int $default = 0): int
    {
        return (int) ($this->input($key) ?? $default);
    }

    /**
     * Get an input value as a float.
     */
    public function float(string $key, float $default = 0.0): float
    {
        return (float) ($this->input($key) ?? $default);
    }

    /**
     * Get an input value as a boolean.
     * Treats "true", "1", "yes", "on" as true.
     */
    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->input($key);
        if ($value === null) {
            return $default;
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    /**
     * Get an input value as an array.
     */
    public function arr(string $key, array $default = []): array
    {
        $value = $this->input($key);
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            return [$value];
        }
        return $default;
    }

    // =========================================================================
    //  Route Parameters
    // =========================================================================

    /**
     * Set route parameters (called by the Router after matching a route).
     *
     * @param array<string, string> $params
     */
    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    /**
     * Get a route parameter (e.g., {id} from /users/{id}).
     *
     * @param string      $key     The route param name.
     * @param string|null $default Default value if not found.
     */
    public function route(string $key, string $default = null): ?string
    {
        return $this->routeParams[$key] ?? $default;
    }

    /**
     * Alias for route() — get a route parameter.
     */
    public function routeParam(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    /**
     * Get all route parameters.
     *
     * @return array<string, string>
     */
    public function routeParams(): array
    {
        return $this->routeParams;
    }

    // =========================================================================
    //  Middleware Attributes
    // =========================================================================

    /**
     * Set an arbitrary attribute on the request (used by middleware).
     * E.g., the Auth middleware sets the current user here.
     *
     * @param string $key
     * @param mixed  $value
     */
    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Get a request attribute set by middleware.
     *
     * @param  string $key     Attribute name.
     * @param  mixed  $default Default value.
     * @return mixed
     */
    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Check if a custom attribute is set.
     */
    public function hasAttribute(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    // =========================================================================
    //  Headers
    // =========================================================================

    /**
     * Get a request header value by name.
     * Header names are case-insensitive (e.g., 'Content-Type' or 'content-type').
     *
     * @param  string      $name    Header name.
     * @param  string|null $default Default value.
     * @return string|null
     */
    public function header(string $name, string $default = null): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        // Special cases that are not prefixed with HTTP_
        if ($name === 'Content-Type') {
            $key = 'CONTENT_TYPE';
        } elseif ($name === 'Content-Length') {
            $key = 'CONTENT_LENGTH';
        }

        return $this->server[$key] ?? $default;
    }

    /**
     * Get all request headers as an associative array.
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        $headers = [];
        foreach ($this->server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = ucwords(strtolower(str_replace('_', '-', substr($key, 5))), '-');
                $headers[$name] = $value;
            }
        }
        $headers['Content-Type']   = $this->server['CONTENT_TYPE'] ?? '';
        $headers['Content-Length'] = $this->server['CONTENT_LENGTH'] ?? '';
        return array_filter($headers);
    }

    /**
     * Check if a header exists and is non-empty.
     */
    public function hasHeader(string $name): bool
    {
        return $this->header($name) !== null;
    }

    /**
     * Get the Authorization header token (Bearer token extraction).
     * Returns the token portion of "Bearer <token>", or null.
     */
    public function bearerToken(): ?string
    {
        $auth = $this->header('Authorization') ?? '';
        if (str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }
        return null;
    }

    // =========================================================================
    //  Content-Type & Accept Negotiation
    // =========================================================================

    /**
     * Get the Content-Type of the request (without parameters like charset).
     * e.g., "application/json"
     */
    public function contentType(): string
    {
        return strtok($this->server['CONTENT_TYPE'] ?? '', ';');
    }

    /**
     * Determine if the request body is JSON (Content-Type: application/json).
     */
    public function isJson(): bool
    {
        return str_contains($this->server['CONTENT_TYPE'] ?? '', 'application/json');
    }

    /**
     * Determine if the request expects a JSON response (Accept: application/json).
     */
    public function wantsJson(): bool
    {
        $accept = $this->header('Accept') ?? '';
        return str_contains($accept, 'application/json') || str_contains($accept, '*/*') && $this->isJson();
    }

    /**
     * Determine if the request is an AJAX (XMLHttpRequest) request.
     */
    public function isAjax(): bool
    {
        return strtolower($this->header('X-Requested-With') ?? '') === 'xmlhttprequest';
    }

    /**
     * Determine if the request is a "safe" read-only method (GET, HEAD, OPTIONS).
     */
    public function isSafe(): bool
    {
        return in_array($this->method, ['GET', 'HEAD', 'OPTIONS'], true);
    }

    /**
     * Determine if this is a preflight OPTIONS request (for CORS).
     */
    public function isPreflight(): bool
    {
        return $this->method === 'OPTIONS';
    }

    // =========================================================================
    //  IP & Client Information
    // =========================================================================

    /**
     * Get the client's IP address.
     * Respects X-Forwarded-For for requests behind a reverse proxy.
     * Set $trustProxy to false to always use REMOTE_ADDR.
     *
     * @param bool $trustProxy Whether to check X-Forwarded-For headers.
     */
    public function ip(bool $trustProxy = true): string
    {
        if ($trustProxy) {
            $forwarded = $this->server['HTTP_X_FORWARDED_FOR'] ?? '';
            if ($forwarded !== '') {
                // X-Forwarded-For may contain a comma-separated list: "client, proxy1, proxy2"
                return trim(explode(',', $forwarded)[0]);
            }
            $realIp = $this->server['HTTP_X_REAL_IP'] ?? '';
            if ($realIp !== '') {
                return $realIp;
            }
        }
        return $this->server['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Get the User-Agent string.
     */
    public function userAgent(): string
    {
        return $this->server['HTTP_USER_AGENT'] ?? '';
    }

    /**
     * Get a server parameter or all server parameters ($_SERVER).
     *
     * @param  string|null $key     Server variable name.
     * @param  mixed       $default Default value.
     * @return mixed
     */
    public function server(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->server;
        }

        return $this->server[$key] ?? $default;
    }

    // =========================================================================
    //  Cookies
    // =========================================================================

    /**
     * Get a cookie value.
     *
     * @param string      $name    Cookie name.
     * @param string|null $default Default value.
     */
    public function cookie(string $name, string $default = null): ?string
    {
        return isset($this->cookies[$name]) ? (string) $this->cookies[$name] : $default;
    }

    /**
     * Get all cookies as an associative array.
     *
     * @return array<string, mixed>
     */
    public function cookies(): array
    {
        return $this->cookies;
    }

    /**
     * Check if a cookie exists.
     */
    public function hasCookie(string $name): bool
    {
        return isset($this->cookies[$name]);
    }

    // =========================================================================
    //  Uploaded Files
    // =========================================================================

    /**
     * Get an uploaded file by input name.
     *
     * @param  string $name The file input field name.
     * @return UploadedFile|null
     */
    public function file(string $name): ?UploadedFile
    {
        if (!isset($this->files[$name])) {
            return null;
        }
        return UploadedFile::fromArray($this->files[$name]);
    }

    /**
     * Check if a file was uploaded with the given input name.
     */
    public function hasFile(string $name): bool
    {
        return isset($this->files[$name])
            && ($this->files[$name]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }

    // =========================================================================
    //  Internal Helpers
    // =========================================================================

    /**
     * Access nested array values using dot notation.
     *
     * @param  array<string, mixed> $data    The data array to traverse.
     * @param  string               $key     Dot-notation key (e.g., "user.address.city").
     * @param  mixed                $default Default value.
     * @return mixed
     */
    private function dotGet(array $data, string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $data)) {
            return $data[$key];
        }

        foreach (explode('.', $key) as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return $default;
            }
            $data = $data[$segment];
        }

        return $data;
    }

    /**
     * Debug dump of request summary.
     */
    public function __toString(): string
    {
        return sprintf('%s %s', $this->method, $this->uri);
    }

    // =========================================================================
    //  Scheme & Host
    // =========================================================================

    /**
     * Get the request scheme (http or https).
     */
    public function scheme(): string
    {
        return $this->isSecure() ? 'https' : 'http';
    }

    /**
     * Get the full scheme + host (e.g., "https://example.com").
     */
    public function schemeAndHost(): string
    {
        return $this->scheme() . '://' . $this->host();
    }
}

