<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Filesystem/Drivers/S3Driver.php
| Module    : Core\Filesystem
| Namespace : NetFusion\Core\Filesystem\Drivers
| Purpose   : Zero-dependency pure PHP AWS S3 REST API driver with SigV4 authentication.
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

namespace NetFusion\Core\Filesystem\Drivers;

use DateTimeInterface;
use DateTimeZone;
use NetFusion\Core\Filesystem\Contracts\FilesystemInterface;
use NetFusion\Core\Filesystem\Exceptions\FileNotFoundException;
use NetFusion\Core\Filesystem\Exceptions\FilesystemException;

/**
 * Class S3Driver
 *
 * Implements Amazon S3 compatible cloud storage (AWS S3, MinIO, Cloudflare R2, DigitalOcean Spaces)
 * using pure PHP 8.2+ cURL and AWS Signature Version 4 (SigV4) with zero external dependencies.
 *
 * @package NetFusion\Core\Filesystem\Drivers
 */
class S3Driver implements FilesystemInterface
{
    protected string $key;
    protected string $secret;
    protected string $region;
    protected string $bucket;
    protected string $endpoint;
    protected bool $usePathStyle;
    protected string $customUrl;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $this->key          = (string) ($config['key'] ?? '');
        $this->secret       = (string) ($config['secret'] ?? '');
        $this->region       = (string) ($config['region'] ?? 'us-east-1');
        $this->bucket       = (string) ($config['bucket'] ?? '');
        $this->endpoint     = rtrim((string) ($config['endpoint'] ?? "https://s3.{$this->region}.amazonaws.com"), '/');
        $this->usePathStyle = (bool) ($config['use_path_style_endpoint'] ?? false);
        $this->customUrl    = rtrim((string) ($config['url'] ?? ''), '/');
    }

    /**
     * Determine if a file exists on S3.
     */
    public function exists(string $path): bool
    {
        $res = $this->request('HEAD', $path);
        return $res['status'] >= 200 && $res['status'] < 300;
    }

    /**
     * Get contents of a file on S3.
     */
    public function get(string $path): string
    {
        $res = $this->request('GET', $path);
        if ($res['status'] === 404) {
            throw new FileNotFoundException($path);
        }
        if ($res['status'] < 200 || $res['status'] >= 300) {
            throw new FilesystemException(sprintf('Failed to retrieve [%s] from S3. HTTP %d', $path, $res['status']));
        }

        return (string) $res['body'];
    }

    /**
     * Read a file as stream.
     */
    public function readStream(string $path)
    {
        $contents = $this->get($path);
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new FilesystemException('Unable to create temporary stream.');
        }
        fwrite($stream, $contents);
        rewind($stream);
        return $stream;
    }

    /**
     * Write contents to S3.
     */
    public function put(string $path, mixed $contents, array $options = []): bool
    {
        $body = is_resource($contents) ? stream_get_contents($contents) : (string) $contents;
        $mime = $options['mimetype'] ?? $this->guessMimeByExtension(strtolower(pathinfo($path, PATHINFO_EXTENSION)));

        $headers = [
            'Content-Type' => $mime,
        ];

        if (isset($options['visibility']) && $options['visibility'] === 'public') {
            $headers['x-amz-acl'] = 'public-read';
        }

        $res = $this->request('PUT', $path, [], $headers, $body);
        return $res['status'] >= 200 && $res['status'] < 300;
    }

    /**
     * Write stream to S3.
     */
    public function writeStream(string $path, mixed $resource, array $options = []): bool
    {
        if (!is_resource($resource)) {
            throw new FilesystemException('Invalid resource provided to writeStream.');
        }

        $contents = stream_get_contents($resource);
        return $this->put($path, $contents, $options);
    }

    /**
     * Prepend data to file on S3.
     */
    public function prepend(string $path, string $data): bool
    {
        $existing = $this->exists($path) ? $this->get($path) : '';
        return $this->put($path, $data . $existing);
    }

    /**
     * Append data to file on S3.
     */
    public function append(string $path, string $data): bool
    {
        $existing = $this->exists($path) ? $this->get($path) : '';
        return $this->put($path, $existing . $data);
    }

    /**
     * Delete file(s) from S3.
     */
    public function delete(string|array $paths): bool
    {
        $paths = is_array($paths) ? $paths : [$paths];
        $allSuccess = true;

        foreach ($paths as $path) {
            $res = $this->request('DELETE', $path);
            if ($res['status'] >= 400 && $res['status'] !== 404) {
                $allSuccess = false;
            }
        }

        return $allSuccess;
    }

    /**
     * Copy object within S3 bucket.
     */
    public function copy(string $from, string $to): bool
    {
        $cleanFrom = ltrim(str_replace('\\', '/', $from), '/');
        $headers = [
            'x-amz-copy-source' => '/' . $this->bucket . '/' . rawurlencode($cleanFrom),
        ];

        $res = $this->request('PUT', $to, [], $headers, '');
        return $res['status'] >= 200 && $res['status'] < 300;
    }

    /**
     * Move object within S3.
     */
    public function move(string $from, string $to): bool
    {
        if ($this->copy($from, $to)) {
            return $this->delete($from);
        }
        return false;
    }

    /**
     * Get object size in bytes.
     */
    public function size(string $path): int
    {
        $res = $this->request('HEAD', $path);
        if ($res['status'] === 404) {
            throw new FileNotFoundException($path);
        }

        return isset($res['headers']['content-length']) ? (int) $res['headers']['content-length'] : 0;
    }

    /**
     * Get object last modified timestamp.
     */
    public function lastModified(string $path): int
    {
        $res = $this->request('HEAD', $path);
        if ($res['status'] === 404) {
            throw new FileNotFoundException($path);
        }

        if (isset($res['headers']['last-modified'])) {
            $time = strtotime($res['headers']['last-modified']);
            return $time !== false ? $time : 0;
        }

        return 0;
    }

    /**
     * Get object MIME type.
     */
    public function mimeType(string $path): ?string
    {
        $res = $this->request('HEAD', $path);
        if ($res['status'] === 404) {
            throw new FileNotFoundException($path);
        }

        if (isset($res['headers']['content-type'])) {
            return $res['headers']['content-type'];
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return $this->guessMimeByExtension($extension);
    }

    /**
     * List files matching directory prefix.
     */
    public function files(string $directory = '', bool $recursive = false): array
    {
        $cleanDir = trim(str_replace('\\', '/', $directory), '/');
        $prefix = $cleanDir === '' ? '' : $cleanDir . '/';

        $query = [
            'list-type' => '2',
            'prefix'    => $prefix,
        ];

        if (!$recursive) {
            $query['delimiter'] = '/';
        }

        $res = $this->request('GET', '', $query);
        if ($res['status'] < 200 || $res['status'] >= 300) {
            return [];
        }

        $files = [];
        if (preg_match_all('/<Key>(.*?)<\/Key>/s', (string) $res['body'], $matches)) {
            foreach ($matches[1] as $key) {
                $decoded = html_entity_decode($key);
                if (!str_ends_with($decoded, '/')) {
                    $files[] = $decoded;
                }
            }
        }

        return $files;
    }

    /**
     * List directories on S3.
     */
    public function directories(string $directory = '', bool $recursive = false): array
    {
        $cleanDir = trim(str_replace('\\', '/', $directory), '/');
        $prefix = $cleanDir === '' ? '' : $cleanDir . '/';

        $query = [
            'list-type' => '2',
            'prefix'    => $prefix,
            'delimiter' => '/',
        ];

        $res = $this->request('GET', '', $query);
        if ($res['status'] < 200 || $res['status'] >= 300) {
            return [];
        }

        $directories = [];
        if (preg_match_all('/<Prefix>(.*?)<\/Prefix>/s', (string) $res['body'], $matches)) {
            foreach ($matches[1] as $pref) {
                $decoded = html_entity_decode($pref);
                if ($decoded !== $prefix && str_ends_with($decoded, '/')) {
                    $directories[] = rtrim($decoded, '/');
                }
            }
        }

        return $directories;
    }

    /**
     * S3 does not have directories, but we can create an empty marker object.
     */
    public function makeDirectory(string $path): bool
    {
        $clean = rtrim(str_replace('\\', '/', $path), '/') . '/';
        return $this->put($clean, '');
    }

    /**
     * Delete all objects with the given directory prefix.
     */
    public function deleteDirectory(string $directory): bool
    {
        $files = $this->files($directory, true);
        if (!empty($files)) {
            $this->delete($files);
        }

        $marker = rtrim(str_replace('\\', '/', $directory), '/') . '/';
        $this->delete($marker);

        return true;
    }

    /**
     * Get public URL for object.
     */
    public function url(string $path): string
    {
        $cleanPath = ltrim(str_replace('\\', '/', $path), '/');

        if ($this->customUrl !== '') {
            return $this->customUrl . '/' . $cleanPath;
        }

        if ($this->usePathStyle) {
            return $this->endpoint . '/' . $this->bucket . '/' . $cleanPath;
        }

        $parts = parse_url($this->endpoint);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? 's3.amazonaws.com';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return sprintf('%s://%s.%s%s/%s', $scheme, $this->bucket, $host, $port, $cleanPath);
    }

    /**
     * Generate an AWS SigV4 Presigned Temporary URL.
     */
    public function temporaryUrl(string $path, int|DateTimeInterface $expiration, array $options = []): string
    {
        $cleanPath = ltrim(str_replace('\\', '/', $path), '/');
        $expiresAt = $expiration instanceof DateTimeInterface ? $expiration->getTimestamp() : $expiration;
        $now = time();
        $ttl = max(1, min(604800, $expiresAt - $now)); // S3 max presigned expiration is 7 days (604800s)

        $dateYmd = gmdate('Ymd', $now);
        $dateTime = gmdate('Ymd\THis\Z', $now);
        $credentialScope = "{$dateYmd}/{$this->region}/s3/aws4_request";

        $host = $this->getHost();
        $canonicalUri = $this->getCanonicalUri($cleanPath);

        $queryParams = [
            'X-Amz-Algorithm'     => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential'    => "{$this->key}/{$credentialScope}",
            'X-Amz-Date'          => $dateTime,
            'X-Amz-Expires'       => (string) $ttl,
            'X-Amz-SignedHeaders' => 'host',
        ];

        ksort($queryParams);

        $canonicalQueryArr = [];
        foreach ($queryParams as $k => $v) {
            $canonicalQueryArr[] = rawurlencode($k) . '=' . rawurlencode($v);
        }
        $canonicalQuery = implode('&', $canonicalQueryArr);

        $canonicalHeaders = "host:{$host}\n";
        $signedHeaders = 'host';
        $hashedPayload = 'UNSIGNED-PAYLOAD';

        $canonicalRequest = "GET\n{$canonicalUri}\n{$canonicalQuery}\n{$canonicalHeaders}\n{$signedHeaders}\n{$hashedPayload}";

        $stringToSign = "AWS4-HMAC-SHA256\n{$dateTime}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);
        $signingKey = $this->getSigningKey($dateYmd);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        $url = $this->url($cleanPath);
        $separator = str_contains($url, '?') ? '&' : '?';

        return sprintf('%s%s%s&X-Amz-Signature=%s', $url, $separator, $canonicalQuery, $signature);
    }

    /**
     * Compute SigV4 signing key.
     */
    public function getSigningKey(string $dateYmd): string
    {
        $kSecret  = 'AWS4' . $this->secret;
        $kDate    = hash_hmac('sha256', $dateYmd, $kSecret, true);
        $kRegion  = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        return hash_hmac('sha256', 'aws4_request', $kService, true);
    }

    /**
     * Calculate SigV4 Authorization Header and payload signature.
     *
     * @param string $method
     * @param string $path
     * @param array<string, string> $query
     * @param array<string, string> $headers
     * @param string $payload
     * @param int|null $timestamp
     * @return array{authorization: string, dateTime: string, host: string, hashedPayload: string}
     */
    public function calculateSignature(
        string $method,
        string $path,
        array $query = [],
        array $headers = [],
        string $payload = '',
        ?int $timestamp = null
    ): array {
        $timestamp = $timestamp ?? time();
        $dateYmd   = gmdate('Ymd', $timestamp);
        $dateTime  = gmdate('Ymd\THis\Z', $timestamp);
        $host      = $this->getHost();
        $scope     = "{$dateYmd}/{$this->region}/s3/aws4_request";

        $cleanPath    = ltrim(str_replace('\\', '/', $path), '/');
        $canonicalUri = $this->getCanonicalUri($cleanPath);

        $hashedPayload = hash('sha256', $payload);

        // Required headers for S3 SigV4
        $headers['host'] = $host;
        $headers['x-amz-date'] = $dateTime;
        $headers['x-amz-content-sha256'] = $hashedPayload;

        // Canonical headers (lowercased and sorted)
        $canonicalHeadersArr = [];
        $signedHeadersArr    = [];
        $lowerHeaders        = [];

        foreach ($headers as $name => $value) {
            $lower = strtolower($name);
            $lowerHeaders[$lower] = trim((string) $value);
        }
        ksort($lowerHeaders);

        foreach ($lowerHeaders as $name => $value) {
            $canonicalHeadersArr[] = "{$name}:{$value}\n";
            $signedHeadersArr[]    = $name;
        }

        $canonicalHeaders = implode('', $canonicalHeadersArr);
        $signedHeaders    = implode(';', $signedHeadersArr);

        // Canonical query string
        ksort($query);
        $queryPieces = [];
        foreach ($query as $k => $v) {
            $queryPieces[] = rawurlencode($k) . '=' . rawurlencode($v);
        }
        $canonicalQuery = implode('&', $queryPieces);

        $canonicalRequest = "{$method}\n{$canonicalUri}\n{$canonicalQuery}\n{$canonicalHeaders}\n{$signedHeaders}\n{$hashedPayload}";

        $stringToSign = "AWS4-HMAC-SHA256\n{$dateTime}\n{$scope}\n" . hash('sha256', $canonicalRequest);
        $signingKey   = $this->getSigningKey($dateYmd);
        $signature    = hash_hmac('sha256', $stringToSign, $signingKey);

        $authorization = sprintf(
            'AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s',
            $this->key,
            $scope,
            $signedHeaders,
            $signature
        );

        return [
            'authorization' => $authorization,
            'dateTime'      => $dateTime,
            'host'          => $host,
            'hashedPayload' => $hashedPayload,
        ];
    }

    /**
     * Dispatch HTTP request to S3 API via cURL.
     *
     * @param string $method
     * @param string $path
     * @param array<string, string> $query
     * @param array<string, string> $headers
     * @param string $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    protected function request(
        string $method,
        string $path,
        array $query = [],
        array $headers = [],
        string $payload = ''
    ): array {
        $sigData = $this->calculateSignature($method, $path, $query, $headers, $payload);

        $headers['Host']                 = $sigData['host'];
        $headers['x-amz-date']           = $sigData['dateTime'];
        $headers['x-amz-content-sha256'] = $sigData['hashedPayload'];
        $headers['Authorization']        = $sigData['authorization'];

        $cleanPath = ltrim(str_replace('\\', '/', $path), '/');
        $url = $this->url($cleanPath);
        if (!empty($query)) {
            $qs = http_build_query($query);
            $url .= (str_contains($url, '?') ? '&' : '?') . $qs;
        }

        $formattedHeaders = [];
        foreach ($headers as $k => $v) {
            $formattedHeaders[] = "{$k}: {$v}";
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $formattedHeaders);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if ($method === 'HEAD') {
            curl_setopt($ch, CURLOPT_NOBODY, true);
        } elseif ($payload !== '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        $response = curl_exec($ch);
        if ($response === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new FilesystemException(sprintf('cURL S3 request failed: %s', $err));
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $headerStr = substr((string) $response, 0, $headerSize);
        $body = substr((string) $response, $headerSize);

        $parsedHeaders = [];
        foreach (explode("\r\n", $headerStr) as $line) {
            if (str_contains($line, ':')) {
                [$hk, $hv] = explode(':', $line, 2);
                $parsedHeaders[strtolower(trim($hk))] = trim($hv);
            }
        }

        return [
            'status'  => $status,
            'headers' => $parsedHeaders,
            'body'    => $body,
        ];
    }

    /**
     * Compute the Host header.
     */
    protected function getHost(): string
    {
        $parts = parse_url($this->endpoint);
        $host = $parts['host'] ?? 's3.amazonaws.com';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        if ($this->usePathStyle) {
            return $host . $port;
        }

        return "{$this->bucket}.{$host}{$port}";
    }

    /**
     * Compute canonical URI for SigV4.
     */
    protected function getCanonicalUri(string $cleanPath): string
    {
        $uri = '';
        if ($this->usePathStyle) {
            $uri .= '/' . $this->bucket;
        }

        if ($cleanPath !== '') {
            $uri .= '/' . implode('/', array_map('rawurlencode', explode('/', $cleanPath)));
        }

        return $uri === '' ? '/' : $uri;
    }

    /**
     * Guess MIME type by extension fallback.
     */
    protected function guessMimeByExtension(string $extension): string
    {
        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'gif'         => 'image/gif',
            'webp'        => 'image/webp',
            'svg'         => 'image/svg+xml',
            'pdf'         => 'application/pdf',
            'json'        => 'application/json',
            'xml'         => 'application/xml',
            'zip'         => 'application/zip',
            'csv'         => 'text/csv',
            'txt', 'log'  => 'text/plain',
            default       => 'application/octet-stream',
        };
    }
}
