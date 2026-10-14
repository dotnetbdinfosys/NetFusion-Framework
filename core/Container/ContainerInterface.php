<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Container/ContainerInterface.php
| Module    : Core\Container
| Namespace : NetFusion\Core\Container
| Purpose   : PSR-11 compliant DI container interface with bind, singleton, make, and call definitions.
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
| File      : core/Container/ContainerInterface.php
| Module    : Core\Container
| Namespace : NetFusion\Core\Container
| Purpose   : PSR-11 compliant DI container interface with bind, singleton, make, and call definitions.
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
| File      : core/Container/ContainerInterface.php
| Module    : Core\Container
| Namespace : NetFusion\Core\Container
| Purpose   : PSR-11 compliant DI container interface with bind, singleton, make, and call definitions.
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

use Psr\Container\ContainerInterface as PsrContainerInterface;

/**
 * NetFusion Container Interface
 *
 * Extends PSR-11's ContainerInterface to add NetFusion-specific
 * binding and resolution methods (bind, singleton, make, etc.)
 *
 * @package NetFusion\Core\Container
 * @version 1.0.0
 */
interface ContainerInterface extends PsrContainerInterface
{
    /**
     * Register a factory binding in the container.
     * Every call to get() or make() will invoke the factory closure.
     *
     * @param string   $abstract The abstract type or alias to bind.
     * @param \Closure $factory  A closure that returns the resolved instance.
     */
    public function bind(string $abstract, \Closure $factory): void;

    /**
     * Register a shared singleton binding.
     * The factory is invoked only once; subsequent calls return the same instance.
     *
     * @param string   $abstract The abstract type or alias to bind.
     * @param \Closure $factory  A closure that returns the resolved instance.
     */
    public function singleton(string $abstract, \Closure $factory): void;

    /**
     * Register an already-resolved instance as a singleton in the container.
     *
     * @param string $abstract The abstract type or alias.
     * @param mixed  $instance The concrete instance to store.
     */
    public function instance(string $abstract, mixed $instance): void;

    /**
     * Resolve (make) a concrete instance from the container.
     * Supports constructor auto-wiring via PHP Reflection.
     *
     * @param  string               $abstract  The abstract type or concrete FQCN.
     * @param  array<string, mixed> $overrides Optional parameter overrides for construction.
     * @return mixed                           The resolved instance.
     *
     * @throws ContainerException If the class cannot be instantiated.
     * @throws NotFoundException  If the binding does not exist and cannot be auto-wired.
     */
    public function make(string $abstract, array $overrides = []): mixed;

    /**
     * Determine if an abstract type has a registered binding.
     *
     * @param  string $abstract The abstract type or alias.
     * @return bool
     */
    public function bound(string $abstract): bool;

    /**
     * Remove a binding from the container.
     *
     * @param string $abstract The abstract type or alias to remove.
     */
    public function forget(string $abstract): void;
}
