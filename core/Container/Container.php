<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Container/Container.php
| Module    : Core\Container
| Namespace : NetFusion\Core\Container
| Purpose   : Reflection-based, auto-wiring Dependency Injection Container with singleton and factory support.
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
| File      : core/Container/Container.php
| Module    : Core\Container
| Namespace : NetFusion\Core\Container
| Purpose   : Reflection-based, auto-wiring Dependency Injection Container with singleton and factory support.
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
| File      : core/Container/Container.php
| Module    : Core\Container
| Namespace : NetFusion\Core\Container
| Purpose   : Reflection-based, auto-wiring Dependency Injection Container with singleton and factory support.
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

use NetFusion\Core\Container\ContainerException;
use NetFusion\Core\Container\ContainerInterface;
use NetFusion\Core\Container\NotFoundException;

/**
 * NetFusion DI Container
 *
 * A modern, PSR-11 compliant Dependency Injection Container featuring:
 *
 *  - Factory bindings (new instance on every resolve)
 *  - Singleton bindings (shared instance across the app lifetime)
 *  - Pre-resolved instance registration
 *  - Automatic constructor auto-wiring via PHP 8.1 Reflection API
 *  - Circular dependency detection
 *  - Contextual parameter override support
 *  - Interface-to-concrete class binding
 *
 * @package NetFusion\Core\Container
 * @version 1.0.0
 */
class Container implements ContainerInterface
{
    /**
     * The global, shared container instance (Application Service Locator).
     */
    private static ?self $instance = null;

    /**
     * Registered factory and singleton bindings.
     *
     * Structure: [abstract => ['factory' => Closure, 'singleton' => bool]]
     *
     * @var array<string, array{factory: \Closure, singleton: bool}>
     */
    private array $bindings = [];

    /**
     * Resolved singleton instances cache.
     *
     * @var array<string, mixed>
     */
    private array $instances = [];

    /**
     * The resolution stack used for circular dependency detection.
     *
     * @var string[]
     */
    private array $buildStack = [];

    /**
     * Set or retrieve the shared global Container instance.
     *
     * @param  self|null $container Optionally replace the global instance.
     * @return self
     */
    public static function setInstance(?self $container = null): self
    {
        if ($container !== null) {
            static::$instance = $container;
        }

        if (static::$instance === null) {
            static::$instance = new self();
        }

        return static::$instance;
    }

    /**
     * Get the shared global Container instance.
     *
     * @return self
     */
    public static function getInstance(): self
    {
        return static::$instance ?? static::setInstance();
    }

    // =========================================================================
    // Binding Registration
    // =========================================================================

    /**
     * {@inheritDoc}
     *
     * Register a transient (factory) binding. A new instance is returned
     * on every call to get() or make().
     */
    public function bind(string $abstract, \Closure $factory): void
    {
        $this->bindings[$abstract] = [
            'factory'   => $factory,
            'singleton' => false,
        ];

        // If a stale singleton instance exists for this abstract, remove it
        unset($this->instances[$abstract]);
    }

    /**
     * {@inheritDoc}
     *
     * Register a singleton binding. The factory closure is invoked exactly
     * once; subsequent calls return the cached instance.
     */
    public function singleton(string $abstract, \Closure $factory): void
    {
        $this->bindings[$abstract] = [
            'factory'   => $factory,
            'singleton' => true,
        ];
    }

    /**
     * {@inheritDoc}
     *
     * Register a pre-constructed object as a singleton. Useful for objects
     * created before the container is fully configured (e.g., the App itself).
     */
    public function instance(string $abstract, mixed $instance): void
    {
        $this->instances[$abstract] = $instance;

        // Also register a pseudo-binding so bound() returns true
        $this->bindings[$abstract] = [
            'factory'   => fn() => $instance,
            'singleton' => true,
        ];
    }

    // =========================================================================
    // Resolution
    // =========================================================================

    /**
     * {@inheritDoc}
     *
     * Resolve a type from the container (PSR-11 entry point).
     * Alias for make() with no parameter overrides.
     *
     * @throws NotFoundException  If the abstract cannot be found or auto-wired.
     * @throws ContainerException If a resolution error occurs.
     */
    public function get(string $id): mixed
    {
        try {
            return $this->make($id);
        } catch (NotFoundException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new ContainerException(
                sprintf('Error while resolving [%s]: %s', $id, $e->getMessage()),
                previous: $e
            );
        }
    }

    /**
     * {@inheritDoc}
     *
     * Resolve a concrete instance from the container, with optional
     * constructor parameter overrides. Handles:
     *   1. Pre-resolved instances (registered via instance())
     *   2. Registered factory / singleton bindings
     *   3. Auto-wiring via PHP Reflection
     *
     * @param  string               $abstract  The type or alias to resolve.
     * @param  array<string, mixed> $overrides Named constructor parameter overrides.
     * @return mixed
     *
     * @throws ContainerException On circular dependency or unresolvable params.
     * @throws NotFoundException  If the class cannot be found or auto-wired.
     */
    public function make(string $abstract, array $overrides = []): mixed
    {
        // 1. Return pre-resolved singleton instances immediately
        if (isset($this->instances[$abstract]) && empty($overrides)) {
            return $this->instances[$abstract];
        }

        // 2. Resolve via a registered binding (factory or singleton)
        if (isset($this->bindings[$abstract])) {
            return $this->resolveBinding($abstract, $overrides);
        }

        // 3. Attempt auto-wiring if the class exists
        if (class_exists($abstract)) {
            return $this->build($abstract, $overrides);
        }

        throw NotFoundException::forAbstract($abstract);
    }

    /**
     * {@inheritDoc}
     */
    public function has(string $id): bool
    {
        return $this->bound($id) || class_exists($id);
    }

    /**
     * {@inheritDoc}
     */
    public function bound(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    /**
     * {@inheritDoc}
     */
    public function forget(string $abstract): void
    {
        unset($this->bindings[$abstract], $this->instances[$abstract]);
    }

    // =========================================================================
    // Internal Resolution Helpers
    // =========================================================================

    /**
     * Resolve a registered binding (factory or singleton).
     *
     * @param  string               $abstract  The abstract type.
     * @param  array<string, mixed> $overrides Constructor parameter overrides.
     * @return mixed
     */
    private function resolveBinding(string $abstract, array $overrides): mixed
    {
        $binding = $this->bindings[$abstract];

        // For singletons, check the instance cache (unless overrides are passed)
        if ($binding['singleton'] && empty($overrides) && isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Invoke the factory closure with the container and overrides as arguments
        $instance = ($binding['factory'])($this, $overrides);

        // Cache the resolved instance if it's a singleton
        if ($binding['singleton'] && empty($overrides)) {
            $this->instances[$abstract] = $instance;
        }

        return $instance;
    }

    /**
     * Build a concrete class instance using PHP Reflection for auto-wiring.
     *
     * This method:
     *   1. Detects circular dependencies via the build stack.
     *   2. Reflects on the constructor and its parameters.
     *   3. Resolves each parameter recursively via the container.
     *   4. Falls back to default parameter values when available.
     *
     * @param  string               $concrete  Fully-qualified class name.
     * @param  array<string, mixed> $overrides Named constructor parameter overrides.
     * @return object                          Fully-constructed instance.
     *
     * @throws ContainerException On circular dependency or non-instantiable class.
     */
    private function build(string $concrete, array $overrides = []): object
    {
        // Circular dependency detection
        if (in_array($concrete, $this->buildStack, true)) {
            $this->buildStack[] = $concrete;
            throw ContainerException::circularDependency($this->buildStack);
        }

        // Push to the build stack
        $this->buildStack[] = $concrete;

        try {
            $reflector = new \ReflectionClass($concrete);

            // Cannot instantiate abstract classes or interfaces directly
            if (!$reflector->isInstantiable()) {
                throw ContainerException::notInstantiable($concrete);
            }

            $constructor = $reflector->getConstructor();

            // No constructor: simply instantiate with no arguments
            if ($constructor === null) {
                return $reflector->newInstanceWithoutConstructor();
            }

            $dependencies = $this->resolveDependencies(
                $concrete,
                $constructor->getParameters(),
                $overrides
            );

            return $reflector->newInstanceArgs($dependencies);

        } finally {
            // Always pop the build stack, even if an exception is thrown
            array_pop($this->buildStack);
        }
    }

    /**
     * Resolve an array of ReflectionParameter objects into concrete values.
     *
     * Resolution order for each parameter:
     *   1. Named override from the $overrides array.
     *   2. Type-hinted class → recursively resolve via make().
     *   3. Default parameter value defined in the method signature.
     *   4. Null if the parameter is nullable.
     *   5. ContainerException for unresolvable primitives.
     *
     * @param  string                    $concrete    The class being built.
     * @param  \ReflectionParameter[]    $parameters  Constructor parameters.
     * @param  array<string, mixed>      $overrides   Named parameter overrides.
     * @return array<int, mixed>                      Ordered array of resolved values.
     *
     * @throws ContainerException If a required primitive cannot be resolved.
     */
    private function resolveDependencies(
        string $concrete,
        array $parameters,
        array $overrides
    ): array {
        $resolved = [];

        foreach ($parameters as $parameter) {
            $name = $parameter->getName();

            // 1. Use a named override if one was provided
            if (array_key_exists($name, $overrides)) {
                $resolved[] = $overrides[$name];
                continue;
            }

            // 2. Get the type hint for class resolution
            $type = $parameter->getType();

            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                // Recursively resolve the class dependency
                try {
                    $resolved[] = $this->make($type->getName(), $overrides);
                } catch (NotFoundException $e) {
                    // If the parameter has a default, fall back to it
                    if ($parameter->isDefaultValueAvailable()) {
                        $resolved[] = $parameter->getDefaultValue();
                    } elseif ($type->allowsNull()) {
                        $resolved[] = null;
                    } else {
                        throw $e;
                    }
                }
                continue;
            }

            // 3. Use the default value for primitives and built-in types
            if ($parameter->isDefaultValueAvailable()) {
                $resolved[] = $parameter->getDefaultValue();
                continue;
            }

            // 4. Nullable primitives → null
            if ($parameter->allowsNull()) {
                $resolved[] = null;
                continue;
            }

            // 5. Cannot resolve — throw
            throw ContainerException::unresolvablePrimitive($concrete, $name);
        }

        return $resolved;
    }

    /**
     * Call a callable (closure, [object, method], 'Class@method') and inject
     * its dependencies automatically. Useful for invoking controller methods,
     * middleware, etc., without manual dependency passing.
     *
     * @param  callable|string      $callable  The callable to invoke.
     * @param  array<string, mixed> $overrides Named parameter overrides.
     * @return mixed                           The return value of the callable.
     *
     * @throws ContainerException If a parameter cannot be resolved.
     */
    public function call(callable|string $callable, array $overrides = []): mixed
    {
        // Resolve 'ClassName@method' string syntax
        if (is_string($callable) && str_contains($callable, '@')) {
            [$class, $method] = explode('@', $callable, 2);
            $callable = [$this->make($class), $method];
        }

        $reflector = match (true) {
            $callable instanceof \Closure => new \ReflectionFunction($callable),
            is_array($callable)           => new \ReflectionMethod($callable[0], $callable[1]),
            default                       => new \ReflectionFunction(\Closure::fromCallable($callable)),
        };

        /** @var \ReflectionParameter[] $parameters */
        $parameters = $reflector->getParameters();

        $class = is_array($callable) ? get_class($callable[0]) : 'Closure';

        $dependencies = $this->resolveDependencies($class, $parameters, $overrides);

        // ReflectionFunction::invokeArgs() takes only the args array.
        // ReflectionMethod::invokeArgs() takes (object, args).
        if ($reflector instanceof \ReflectionMethod) {
            return $reflector->invokeArgs($callable[0], $dependencies);
        }

        return $reflector->invokeArgs($dependencies);
    }

    /**
     * Prevent unintended cloning of the container.
     */
    private function __clone() {}
}
