<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Filesystem/Exceptions/FilesystemException.php
| Module    : Core\Filesystem
| Namespace : NetFusion\Core\Filesystem\Exceptions
| Purpose   : Base exception class for all filesystem and storage operations.
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

use RuntimeException;

/**
 * Class FilesystemException
 *
 * Base exception thrown when a filesystem operation fails.
 *
 * @package NetFusion\Core\Filesystem\Exceptions
 */
class FilesystemException extends RuntimeException
{
}
