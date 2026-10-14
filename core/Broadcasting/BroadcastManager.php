<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Broadcasting/BroadcastManager.php
| Module    : Broadcasting
| Namespace : NetFusion\Core\Broadcasting
| Purpose   : Central real-time event broadcasting manager, channel authorization, and driver registry.
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

namespace NetFusion\Core\Broadcasting;

use NetFusion\Core\Application;
use NetFusion\Core\Broadcasting\Contracts\BroadcastDriverInterface;
use NetFusion\Core\Broadcasting\Contracts\ChannelInterface;
use NetFusion\Core\Broadcasting\Contracts\ShouldBroadcast;
use NetFusion\Core\Broadcasting\Drivers\LogBroadcastDriver;
use NetFusion\Core\Broadcasting\Drivers\NullBroadcastDriver;
use NetFusion\Core\Broadcasting\Drivers\SseBroadcastDriver;
use NetFusion\Core\Http\Request;
use RuntimeException;

/**
 * Class BroadcastManager
 *
 * Orchestrates multi-driver event broadcasting, channel authentication gates,
 * and automatic dispatching of ShouldBroadcast events.
 *
 * @package NetFusion\Core\Broadcasting
 */
class BroadcastManager
{
    /**
     * Active driver instances.
     *
     * @var array<string, BroadcastDriverInterface>
     */
    protected array $drivers = [];

    /**
     * Channel authorization callbacks indexed by pattern.
     *
     * @var array<string, callable>
     */
    protected array $channelAuthorizers = [];

    /**
     * Custom creator callbacks.
     *
     * @var array<string, callable>
     */
    protected array $customCreators = [];

    public function __construct(
        protected Application $app
    ) {}

    /**
     * Get a broadcast driver instance.
     *
     * @param string|null $driver
     * @return BroadcastDriverInterface
     */
    public function driver(?string $driver = null): BroadcastDriverInterface
    {
        $driver = $driver ?? $this->getDefaultDriver();

        if (!isset($this->drivers[$driver])) {
            $this->drivers[$driver] = $this->createDriver($driver);
        }

        return $this->drivers[$driver];
    }

    /**
     * Register a channel authorization callback.
     * Supports parameter patterns such as 'private-user.{id}' or 'orders.{orderId}'.
     *
     * @param string   $channelPattern
     * @param callable $callback fn($user, ...$params): bool|array
     * @return $this
     */
    public function channel(string $channelPattern, callable $callback): self
    {
        $this->channelAuthorizers[$channelPattern] = $callback;
        return $this;
    }

    /**
     * Authenticate an incoming request for a specific channel.
     *
     * @param Request $request
     * @param string  $channelName
     * @return bool|array True or array of user presence data if authorized; false otherwise.
     */
    public function authenticate(Request $request, string $channelName): bool|array
    {
        // Public channels are always open
        if (!str_starts_with($channelName, 'private-') && !str_starts_with($channelName, 'presence-')) {
            return true;
        }

        $user = $request->getAttribute('user') ?? (function_exists('auth') && auth()->check() ? auth()->user() : null);

        foreach ($this->channelAuthorizers as $pattern => $callback) {
            $regex = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^.]+)', $pattern);
            $regex = "#^{$regex}$#";

            if (preg_match($regex, $channelName, $matches)) {
                $params = array_filter($matches, fn($key) => !is_int($key), ARRAY_FILTER_USE_KEY);
                $result = call_user_func_array($callback, array_merge([$user], array_values($params)));
                return is_array($result) ? $result : (bool) $result;
            }
        }

        // If authenticated user is present but no specific gate matched, default allow private channels
        return $user !== null;
    }

    /**
     * Broadcast an event to one or more channels.
     *
     * @param array<int, ChannelInterface|string>|ChannelInterface|string $channels
     * @param string                                                      $event
     * @param array<string, mixed>                                        $payload
     * @param string|null                                                 $driver
     */
    public function broadcast(array|ChannelInterface|string $channels, string $event, array $payload = [], ?string $driver = null): void
    {
        $channelsList = is_array($channels) ? $channels : [$channels];
        $this->driver($driver)->broadcast($channelsList, $event, $payload);
    }

    /**
     * Broadcast an event implementing ShouldBroadcast.
     */
    public function broadcastEvent(ShouldBroadcast $event): void
    {
        $channels = $event->broadcastOn();
        if (empty($channels)) {
            return;
        }

        $eventName = $event->broadcastAs() ?? class_basename($event);
        $payload   = $event->broadcastWith();

        $this->broadcast($channels, $eventName, $payload);
    }

    /**
     * Register a custom driver creator.
     */
    public function extend(string $driver, callable $callback): self
    {
        $this->customCreators[$driver] = $callback;
        return $this;
    }

    /**
     * Get the default broadcast driver name from config.
     */
    public function getDefaultDriver(): string
    {
        return (string) config('broadcasting.default', 'sse');
    }

    /**
     * Create a new driver instance.
     */
    protected function createDriver(string $driver): BroadcastDriverInterface
    {
        if (isset($this->customCreators[$driver])) {
            return call_user_func($this->customCreators[$driver], $this->app);
        }

        return match ($driver) {
            'sse'              => new SseBroadcastDriver(),
            'log'              => new LogBroadcastDriver(),
            'null'             => new NullBroadcastDriver(),
            'socket', 'websocket' => new \NetFusion\Core\Broadcasting\Drivers\SocketBroadcastDriver(),
            default            => throw new RuntimeException("Unsupported broadcast driver [{$driver}]."),
        };
    }
}
