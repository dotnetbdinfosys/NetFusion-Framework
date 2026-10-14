<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Container/NotFoundException.php
| Module    : Core\Container
| Namespace : NetFusion\Core\Container
| Purpose   : PSR-11 NotFoundException thrown when an identifier cannot be resolved or auto-wired.
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
| File      : core/Container/NotFoundException.php
| Module    : Core\Container
| Namespace : NetFusion\Core\Container
| Purpose   : PSR-11 NotFoundException thrown when an identifier cannot be resolved or auto-wired.
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
| File      : core/Container/NotFoundException.php
| Module    : Core\Container
| Namespace : NetFusion\Core\Container
| Purpose   : PSR-11 NotFoundException thrown when an identifier cannot be resolved or auto-wired.
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

namespace NetFusion\Core\Container;

/**
 * NotFoundException
 *
 * Thrown when an identifier is not found in the container and
 * cannot be auto-wired (e.g., the class does not exist, is not
 * loadable, or is an interface with no bound implementation).
 *
 * Implements PSR-11's NotFoundExceptionInterface.
 *
 * @package NetFusion\Core\Container
 * @version 1.0.0
 */
class NotFoundException extends \RuntimeException implements \Psr\Container\NotFoundExceptionInterface
{
    /**
     * Create a NotFoundException for a missing binding.
     *
     * @param  string $abstract The abstract type / alias that was not found.
     * @return static
     */
    public static function forAbstract(string $abstract): static
    {
        return new static(
            sprintf(
                'No binding found for [%s] in the container, ' .
                'and the class either does not exist or cannot be auto-wired.',
                $abstract
            )
        );
    }
}
