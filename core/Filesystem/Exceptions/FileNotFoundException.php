<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Filesystem/Exceptions/FileNotFoundException.php
| Module    : Core\Filesystem
| Namespace : NetFusion\Core\Filesystem\Exceptions
| Purpose   : Exception thrown when a requested file does not exist on disk.
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

namespace NetFusion\Core\Filesystem\Exceptions;

/**
 * Class FileNotFoundException
 *
 * Thrown when trying to access or read a non-existent file on a disk.
 *
 * @package NetFusion\Core\Filesystem\Exceptions
 */
class FileNotFoundException extends FilesystemException
{
    public function __construct(string $path, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(sprintf('File not found at path: [%s]', $path), $code, $previous);
    }
}
