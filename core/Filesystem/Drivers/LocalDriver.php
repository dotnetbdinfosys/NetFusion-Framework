<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Filesystem/Drivers/LocalDriver.php
| Module    : Core\Filesystem
| Namespace : NetFusion\Core\Filesystem\Drivers
| Purpose   : High-performance local filesystem driver with path isolation, stream support, and URL resolution.
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
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use NetFusion\Core\Filesystem\Contracts\FilesystemInterface;
use NetFusion\Core\Filesystem\Exceptions\FileNotFoundException;
use NetFusion\Core\Filesystem\Exceptions\FilesystemException;

/**
 * Class LocalDriver
 *
 * Implements filesystem storage on the local operating system filesystem.
 *
 * @package NetFusion\Core\Filesystem\Drivers
 */
class LocalDriver implements FilesystemInterface
{
    protected string $root;

    /**
     * @param string $root Base directory path for this disk.
     * @param string $url Base URL for publicly accessible files.
     * @param int $permissions Default directory permissions.
     */
    public function __construct(
        string $root,
        protected string $url = '',
        protected int $permissions = 0755
    ) {
        $this->root = $this->normalizePath($root);
        if (!is_dir($this->root)) {
            mkdir($this->root, $this->permissions, true);
        }
    }

    /**
     * Get the root directory of this disk.
     */
    public function getRoot(): string
    {
        return $this->root;
    }

    /**
     * Determine if a file or directory exists.
     */
    public function exists(string $path): bool
    {
        return file_exists($this->fullPath($path));
    }

    /**
     * Get the contents of a file.
     */
    public function get(string $path): string
    {
        $full = $this->fullPath($path);
        if (!file_exists($full) || is_dir($full)) {
            throw new FileNotFoundException($path);
        }

        $contents = file_get_contents($full);
        if ($contents === false) {
            throw new FilesystemException(sprintf('Failed to read file at [%s].', $path));
        }

        return $contents;
    }

    /**
     * Read a file as a stream resource.
     */
    public function readStream(string $path)
    {
        $full = $this->fullPath($path);
        if (!file_exists($full) || is_dir($full)) {
            throw new FileNotFoundException($path);
        }

        $stream = fopen($full, 'rb');
        if ($stream === false) {
            throw new FilesystemException(sprintf('Failed to open stream for [%s].', $path));
        }

        return $stream;
    }

    /**
     * Write contents to a file.
     */
    public function put(string $path, mixed $contents, array $options = []): bool
    {
        if (is_resource($contents)) {
            return $this->writeStream($path, $contents, $options);
        }

        $full = $this->fullPath($path);
        $this->ensureDirectoryExists(dirname($full));

        $result = file_put_contents($full, (string) $contents, LOCK_EX);
        return $result !== false;
    }

    /**
     * Write a stream resource to a file.
     */
    public function writeStream(string $path, mixed $resource, array $options = []): bool
    {
        if (!is_resource($resource)) {
            throw new FilesystemException('Invalid resource provided to writeStream.');
        }

        $full = $this->fullPath($path);
        $this->ensureDirectoryExists(dirname($full));

        $destination = fopen($full, 'wb');
        if ($destination === false) {
            throw new FilesystemException(sprintf('Failed to open destination stream for [%s].', $path));
        }

        $bytesCopied = stream_copy_to_stream($resource, $destination);
        fclose($destination);

        return $bytesCopied !== false;
    }

    /**
     * Prepend content to an existing file.
     */
    public function prepend(string $path, string $data): bool
    {
        if ($this->exists($path)) {
            return $this->put($path, $data . $this->get($path));
        }

        return $this->put($path, $data);
    }

    /**
     * Append content to an existing file.
     */
    public function append(string $path, string $data): bool
    {
        $full = $this->fullPath($path);
        $this->ensureDirectoryExists(dirname($full));

        $result = file_put_contents($full, $data, FILE_APPEND | LOCK_EX);
        return $result !== false;
    }

    /**
     * Delete one or more files.
     */
    public function delete(string|array $paths): bool
    {
        $paths = is_array($paths) ? $paths : [$paths];
        $success = true;

        foreach ($paths as $path) {
            $full = $this->fullPath($path);
            if (file_exists($full) && !is_dir($full)) {
                if (!@unlink($full)) {
                    $success = false;
                }
            }
        }

        return $success;
    }

    /**
     * Copy a file to a new location.
     */
    public function copy(string $from, string $to): bool
    {
        $fromFull = $this->fullPath($from);
        if (!file_exists($fromFull) || is_dir($fromFull)) {
            throw new FileNotFoundException($from);
        }

        $toFull = $this->fullPath($to);
        $this->ensureDirectoryExists(dirname($toFull));

        return copy($fromFull, $toFull);
    }

    /**
     * Move a file to a new location.
     */
    public function move(string $from, string $to): bool
    {
        $fromFull = $this->fullPath($from);
        if (!file_exists($fromFull) || is_dir($fromFull)) {
            throw new FileNotFoundException($from);
        }

        $toFull = $this->fullPath($to);
        $this->ensureDirectoryExists(dirname($toFull));

        return rename($fromFull, $toFull);
    }

    /**
     * Get the file size in bytes.
     */
    public function size(string $path): int
    {
        $full = $this->fullPath($path);
        if (!file_exists($full) || is_dir($full)) {
            throw new FileNotFoundException($path);
        }

        $size = filesize($full);
        return $size !== false ? $size : 0;
    }

    /**
     * Get the last modified timestamp.
     */
    public function lastModified(string $path): int
    {
        $full = $this->fullPath($path);
        if (!file_exists($full) || is_dir($full)) {
            throw new FileNotFoundException($path);
        }

        $time = filemtime($full);
        return $time !== false ? $time : 0;
    }

    /**
     * Get the MIME type of a file.
     */
    public function mimeType(string $path): ?string
    {
        $full = $this->fullPath($path);
        if (!file_exists($full) || is_dir($full)) {
            throw new FileNotFoundException($path);
        }

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = finfo_file($finfo, $full);
                finfo_close($finfo);
                if ($mime !== false) {
                    return $mime;
                }
            }
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return $this->guessMimeByExtension($extension);
    }

    /**
     * Get an array of all files in a directory.
     */
    public function files(string $directory = '', bool $recursive = false): array
    {
        $dirFull = $this->fullPath($directory);
        if (!is_dir($dirFull)) {
            return [];
        }

        $results = [];

        if ($recursive) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dirFull, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                if ($item->isFile()) {
                    $rel = substr($this->normalizePath($item->getPathname()), strlen($this->root) + 1);
                    $results[] = str_replace('\\', '/', $rel);
                }
            }
        } else {
            $iterator = new FilesystemIterator($dirFull, FilesystemIterator::SKIP_DOTS);
            foreach ($iterator as $item) {
                if ($item->isFile()) {
                    $rel = substr($this->normalizePath($item->getPathname()), strlen($this->root) + 1);
                    $results[] = str_replace('\\', '/', $rel);
                }
            }
        }

        sort($results);
        return $results;
    }

    /**
     * Get all directories within a given directory.
     */
    public function directories(string $directory = '', bool $recursive = false): array
    {
        $dirFull = $this->fullPath($directory);
        if (!is_dir($dirFull)) {
            return [];
        }

        $results = [];

        if ($recursive) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dirFull, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                if ($item->isDir()) {
                    $rel = substr($this->normalizePath($item->getPathname()), strlen($this->root) + 1);
                    $results[] = str_replace('\\', '/', $rel);
                }
            }
        } else {
            $iterator = new FilesystemIterator($dirFull, FilesystemIterator::SKIP_DOTS);
            foreach ($iterator as $item) {
                if ($item->isDir()) {
                    $rel = substr($this->normalizePath($item->getPathname()), strlen($this->root) + 1);
                    $results[] = str_replace('\\', '/', $rel);
                }
            }
        }

        sort($results);
        return $results;
    }

    /**
     * Create a directory.
     */
    public function makeDirectory(string $path): bool
    {
        $full = $this->fullPath($path);
        if (is_dir($full)) {
            return true;
        }

        return mkdir($full, $this->permissions, true);
    }

    /**
     * Recursively delete a directory.
     */
    public function deleteDirectory(string $directory): bool
    {
        $full = $this->fullPath($directory);
        if (!is_dir($full)) {
            return false;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($full, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        return @rmdir($full);
    }

    /**
     * Get the publicly accessible URL for a given file path.
     */
    public function url(string $path): string
    {
        $baseUrl = rtrim($this->url, '/');
        $cleanPath = ltrim(str_replace('\\', '/', $path), '/');

        if ($baseUrl === '') {
            return '/' . $cleanPath;
        }

        return $baseUrl . '/' . $cleanPath;
    }

    /**
     * Get a temporary / signed URL for a file.
     */
    public function temporaryUrl(string $path, int|DateTimeInterface $expiration, array $options = []): string
    {
        $expires = $expiration instanceof DateTimeInterface ? $expiration->getTimestamp() : $expiration;
        $cleanPath = ltrim(str_replace('\\', '/', $path), '/');
        $key = (string) (config('app.key') ?? 'netfusion-storage-secret-key-32chars');

        $signature = hash_hmac('sha256', $cleanPath . '::' . $expires, $key);
        $url = $this->url($cleanPath);
        $separator = str_contains($url, '?') ? '&' : '?';

        return sprintf('%s%sexpires=%d&signature=%s', $url, $separator, $expires, $signature);
    }

    /**
     * Resolve the absolute filesystem path and enforce root isolation.
     *
     * @param string $path
     * @return string
     * @throws FilesystemException
     */
    protected function fullPath(string $path): string
    {
        // Strip null bytes
        $path = str_replace("\0", '', $path);

        // Normalize directory separators
        $path = str_replace('\\', '/', $path);

        // Disallow path traversal attacks
        $segments = explode('/', $path);
        $cleanSegments = [];

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                throw new FilesystemException(sprintf('Path traversal detected in path: [%s].', $path));
            }
            $cleanSegments[] = $segment;
        }

        $rel = implode('/', $cleanSegments);
        return $rel === '' ? $this->root : $this->root . '/' . $rel;
    }

    /**
     * Normalize directory paths to forward slashes.
     */
    protected function normalizePath(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }

    /**
     * Ensure parent directory exists.
     */
    protected function ensureDirectoryExists(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, $this->permissions, true);
        }
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
            'html', 'htm' => 'text/html',
            'css'         => 'text/css',
            'js'          => 'text/javascript',
            'mp3'         => 'audio/mpeg',
            'mp4'         => 'video/mp4',
            default       => 'application/octet-stream',
        };
    }
}
