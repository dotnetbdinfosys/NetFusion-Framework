<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Filesystem/Contracts/FilesystemInterface.php
| Module    : Core\Filesystem
| Namespace : NetFusion\Core\Filesystem\Contracts
| Purpose   : Primary contract defining filesystem operations across local and cloud storage disks.
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

namespace NetFusion\Core\Filesystem\Contracts;

/**
 * Interface FilesystemInterface
 *
 * Defines the standard abstraction for all filesystem disks (Local, S3, etc.).
 *
 * @package NetFusion\Core\Filesystem\Contracts
 */
interface FilesystemInterface
{
    /**
     * Determine if a file or directory exists.
     *
     * @param string $path
     * @return bool
     */
    public function exists(string $path): bool;

    /**
     * Get the contents of a file.
     *
     * @param string $path
     * @return string
     * @throws \NetFusion\Core\Filesystem\Exceptions\FileNotFoundException
     */
    public function get(string $path): string;

    /**
     * Read a file as a stream resource.
     *
     * @param string $path
     * @return resource
     * @throws \NetFusion\Core\Filesystem\Exceptions\FileNotFoundException
     */
    public function readStream(string $path);

    /**
     * Write contents to a file.
     *
     * @param string $path
     * @param string|resource $contents
     * @param array<string, mixed> $options
     * @return bool
     */
    public function put(string $path, mixed $contents, array $options = []): bool;

    /**
     * Write a stream resource to a file.
     *
     * @param string $path
     * @param resource $resource
     * @param array<string, mixed> $options
     * @return bool
     */
    public function writeStream(string $path, mixed $resource, array $options = []): bool;

    /**
     * Prepend content to an existing file.
     *
     * @param string $path
     * @param string $data
     * @return bool
     */
    public function prepend(string $path, string $data): bool;

    /**
     * Append content to an existing file.
     *
     * @param string $path
     * @param string $data
     * @return bool
     */
    public function append(string $path, string $data): bool;

    /**
     * Delete one or more files.
     *
     * @param string|array<int, string> $paths
     * @return bool
     */
    public function delete(string|array $paths): bool;

    /**
     * Copy a file to a new location.
     *
     * @param string $from
     * @param string $to
     * @return bool
     */
    public function copy(string $from, string $to): bool;

    /**
     * Move a file to a new location.
     *
     * @param string $from
     * @param string $to
     * @return bool
     */
    public function move(string $from, string $to): bool;

    /**
     * Get the file size in bytes.
     *
     * @param string $path
     * @return int
     */
    public function size(string $path): int;

    /**
     * Get the last modified timestamp.
     *
     * @param string $path
     * @return int
     */
    public function lastModified(string $path): int;

    /**
     * Get the MIME type of a file.
     *
     * @param string $path
     * @return string|null
     */
    public function mimeType(string $path): ?string;

    /**
     * Get an array of all files in a directory.
     *
     * @param string $directory
     * @param bool $recursive
     * @return array<int, string>
     */
    public function files(string $directory = '', bool $recursive = false): array;

    /**
     * Get all directories within a given directory.
     *
     * @param string $directory
     * @param bool $recursive
     * @return array<int, string>
     */
    public function directories(string $directory = '', bool $recursive = false): array;

    /**
     * Create a directory.
     *
     * @param string $path
     * @return bool
     */
    public function makeDirectory(string $path): bool;

    /**
     * Recursively delete a directory.
     *
     * @param string $directory
     * @return bool
     */
    public function deleteDirectory(string $directory): bool;

    /**
     * Get the publicly accessible URL for a given file path.
     *
     * @param string $path
     * @return string
     */
    public function url(string $path): string;

    /**
     * Get a temporary / signed URL for a file.
     *
     * @param string $path
     * @param int|\DateTimeInterface $expiration Unix timestamp or DateTimeInterface
     * @param array<string, mixed> $options
     * @return string
     */
    public function temporaryUrl(string $path, int|\DateTimeInterface $expiration, array $options = []): string;
}
