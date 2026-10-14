<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Events/Dispatcher.php
| Module    : Core\Events
| Namespace : NetFusion\Core\Events
| Purpose   : Central event dispatcher registering listeners and firing sync events with auto-wiring.
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

namespace NetFusion\Core\Events;

use NetFusion\Core\Application;

/**
 * NetFusion Event Dispatcher
 *
 * Dispatches events to registered listeners with dependency injection and wildcard matching.
 *
 * @package NetFusion\Core\Events
 * @version 1.0.0
 */
class Dispatcher
{
    /**
     * Registered event listeners indexed by event name.
     *
     * @var array<string, array<int, mixed>>
     */
    protected array $listeners = [];

    /**
     * Wildcard event listeners.
     *
     * @var array<string, array<int, mixed>>
     */
    protected array $wildcards = [];

    /**
     * @param Application $app
     */
    public function __construct(
        protected readonly Application $app
    ) {}

    /**
     * Register an event listener.
     *
     * @param string|string[] $events   Event name or class name.
     * @param mixed           $listener Closure, 'Class@method', or [Class, method].
     */
    public function listen(string|array $events, mixed $listener): static
    {
        foreach ((array) $events as $event) {
            if (str_contains($event, '*')) {
                $this->wildcards[$event][] = $listener;
            } else {
                $this->listeners[$event][] = $listener;
            }
        }

        return $this;
    }

    /**
     * Fire an event and call its listeners.
     *
     * @param  string|object $event   Event instance or event name string.
     * @param  mixed         $payload Payload if string event name provided.
     * @return array<int, mixed> Responses from listeners.
     */
    public function dispatch(string|object $event, mixed $payload = []): array
    {
        $eventName = is_object($event) ? get_class($event) : $event;
        $eventObj  = is_object($event) ? $event : null;

        $listeners = $this->getListeners($eventName);
        $responses = [];

        foreach ($listeners as $listener) {
            // Check if propagation was stopped
            if ($eventObj instanceof Event && $eventObj->isPropagationStopped()) {
                break;
            }

            $response = $this->executeListener($listener, $event, $payload);
            $responses[] = $response;

            // If listener returned false, stop further propagation
            if ($response === false) {
                if ($eventObj instanceof Event) {
                    $eventObj->stopPropagation();
                }
                break;
            }
        }

        // Automatically broadcast event if it implements ShouldBroadcast contract
        if ($eventObj instanceof \NetFusion\Core\Broadcasting\Contracts\ShouldBroadcast) {
            if ($this->app->bound(\NetFusion\Core\Broadcasting\BroadcastManager::class)) {
                /** @var \NetFusion\Core\Broadcasting\BroadcastManager $broadcaster */
                $broadcaster = $this->app->make(\NetFusion\Core\Broadcasting\BroadcastManager::class);
                $broadcaster->broadcastEvent($eventObj);
            }
        }

        return $responses;
    }

    /**
     * Get all listeners matching the event name (including wildcards).
     *
     * @param  string $eventName
     * @return array<int, mixed>
     */
    public function getListeners(string $eventName): array
    {
        $listeners = $this->listeners[$eventName] ?? [];

        foreach ($this->wildcards as $pattern => $wildcardListeners) {
            $regex = '/^' . str_replace(['\\', '*'], ['\\\\', '.*'], $pattern) . '$/';
            if (preg_match($regex, $eventName)) {
                $listeners = array_merge($listeners, $wildcardListeners);
            }
        }

        return $listeners;
    }

    /**
     * Execute an individual listener.
     *
     * @param  mixed         $listener
     * @param  string|object $event
     * @param  mixed         $payload
     * @return mixed
     */
    protected function executeListener(mixed $listener, string|object $event, mixed $payload): mixed
    {
        // 1. Closure or invokable object
        if ($listener instanceof \Closure || (is_object($listener) && is_callable($listener))) {
            return $listener($event, $payload);
        }

        // 2. 'ListenerClass@method' string syntax
        if (is_string($listener) && str_contains($listener, '@')) {
            [$class, $method] = explode('@', $listener, 2);
            if (is_subclass_of($class, \NetFusion\Core\Queue\Contracts\ShouldQueue::class)) {
                return queue(new \NetFusion\Core\Queue\CallQueuedListener($class, $method, $event, $payload));
            }
            $instance = $this->app->make($class);
            return $instance->$method($event, $payload);
        }

        // 3. [ListenerClass::class, 'method'] array syntax
        if (is_array($listener)) {
            $class = is_string($listener[0]) ? $listener[0] : get_class($listener[0]);
            $method = $listener[1];
            if (is_subclass_of($class, \NetFusion\Core\Queue\Contracts\ShouldQueue::class)) {
                return queue(new \NetFusion\Core\Queue\CallQueuedListener($class, $method, $event, $payload));
            }
            $instance = is_string($listener[0]) ? $this->app->make($listener[0]) : $listener[0];
            return $instance->$method($event, $payload);
        }

        // 4. Class name with default handle() method
        if (is_string($listener) && class_exists($listener)) {
            if (is_subclass_of($listener, \NetFusion\Core\Queue\Contracts\ShouldQueue::class)) {
                return queue(new \NetFusion\Core\Queue\CallQueuedListener($listener, 'handle', $event, $payload));
            }
            $instance = $this->app->make($listener);
            if (method_exists($instance, 'handle')) {
                return $instance->handle($event, $payload);
            }
        }

        return null;
    }

}
