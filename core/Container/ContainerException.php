<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Container/ContainerException.php
| Module    : Core\Container
| Namespace : NetFusion\Core\Container
| Purpose   : Exception thrown when a generic container resolution or dependency error occurs.
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
| File      : core/Container/ContainerException.php
| Module    : Core\Container
| Namespace : NetFusion\Core\Container
| Purpose   : Exception thrown when a generic container resolution or dependency error occurs.
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
| File      : core/Container/ContainerException.php
| Module    : Core\Container
| Namespace : NetFusion\Core\Container
| Purpose   : Exception thrown when a generic container resolution or dependency error occurs.
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
 * ContainerException
 *
 * Thrown when a general error occurs during container operations,
 * such as attempting to instantiate an abstract class, when a
 * circular dependency is detected, or when a constructor parameter
 * cannot be resolved.
 *
 * Implements PSR-11's ContainerExceptionInterface.
 *
 * @package NetFusion\Core\Container
 * @version 1.0.0
 */
class ContainerException extends \RuntimeException implements \Psr\Container\ContainerExceptionInterface
{
    /**
     * Create a new container exception for a circular dependency.
     *
     * @param  string[] $buildStack Current resolution stack (for reporting the cycle).
     * @return static
     */
    public static function circularDependency(array $buildStack): static
    {
        $chain = implode(' -> ', $buildStack);

        return new static(
            sprintf('Circular dependency detected while resolving: [%s].', $chain)
        );
    }

    /**
     * Create a new container exception for an unresolvable primitive parameter.
     *
     * @param  string $abstract  The class being resolved.
     * @param  string $parameter The name of the primitive parameter.
     * @return static
     */
    public static function unresolvablePrimitive(string $abstract, string $parameter): static
    {
        return new static(
            sprintf(
                'Cannot resolve primitive parameter [$%s] in class [%s]. ' .
                'Please provide a default value or bind it explicitly.',
                $parameter,
                $abstract
            )
        );
    }

    /**
     * Create a new container exception when a class is not instantiable
     * (e.g., it is abstract, an interface, or lacks a public constructor).
     *
     * @param  string $abstract The abstract class / interface name.
     * @return static
     */
    public static function notInstantiable(string $abstract): static
    {
        return new static(
            sprintf(
                'Target [%s] is not instantiable. ' .
                'Bind it to a concrete implementation via $container->bind() or $container->singleton().',
                $abstract
            )
        );
    }
}
