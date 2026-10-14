<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Filesystem/Storage.php
| Module    : Core\Filesystem
| Namespace : NetFusion\Core\Filesystem
| Purpose   : Static facade accessor for the StorageManager and default filesystem disk.
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

namespace NetFusion\Core\Filesystem;

use NetFusion\Core\Filesystem\Contracts\FilesystemInterface;

/**
 * Class Storage
 *
 * Facade providing static access to the active StorageManager and default disk.
 *
 * @method static FilesystemInterface disk(?string $name = null)
 * @method static bool exists(string $path)
 * @method static string get(string $path)
 * @method static mixed readStream(string $path)
 * @method static bool put(string $path, mixed $contents, array $options = [])
 * @method static bool writeStream(string $path, mixed $resource, array $options = [])
 * @method static bool prepend(string $path, string $data)
 * @method static bool append(string $path, string $data)
 * @method static bool delete(string|array $paths)
 * @method static bool copy(string $from, string $to)
 * @method static bool move(string $from, string $to)
 * @method static int size(string $path)
 * @method static int lastModified(string $path)
 * @method static string|null mimeType(string $path)
 * @method static array files(string $directory = '', bool $recursive = false)
 * @method static array directories(string $directory = '', bool $recursive = false)
 * @method static bool makeDirectory(string $path)
 * @method static bool deleteDirectory(string $directory)
 * @method static string url(string $path)
 * @method static string temporaryUrl(string $path, int|\DateTimeInterface $expiration, array $options = [])
 *
 * @package NetFusion\Core\Filesystem
 */
class Storage
{
    /**
     * Get the StorageManager instance from the application container.
     */
    public static function getFacadeRoot(): StorageManager
    {
        return app('storage');
    }

    /**
     * Get a specific disk instance.
     *
     * @param string|null $name
     * @return FilesystemInterface
     */
    public static function disk(?string $name = null): FilesystemInterface
    {
        return static::getFacadeRoot()->disk($name);
    }

    /**
     * Handle dynamic static calls to the default disk.
     *
     * @param string $method
     * @param array<mixed> $arguments
     * @return mixed
     */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        return static::getFacadeRoot()->$method(...$arguments);
    }
}
