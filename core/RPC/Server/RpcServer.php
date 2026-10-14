<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/RPC/Server/RpcServer.php
| Module    : RPC & Service Mesh
| Namespace : NetFusion\Core\RPC\Server
| Purpose   : High-performance pure-PHP stream socket RPC Server daemon.
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

namespace NetFusion\Core\RPC\Server;

use NetFusion\Core\Container\Container;
use NetFusion\Core\RPC\Contracts\RpcServerInterface;
use NetFusion\Core\RPC\Contracts\ServiceRegistryInterface;
use NetFusion\Core\RPC\Registry\ServiceInstance;
use NetFusion\Core\RPC\RpcException;
use NetFusion\Core\RPC\RpcRequest;
use NetFusion\Core\RPC\RpcResponse;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

class RpcServer implements RpcServerInterface
{
    /**
     * @var array<string, object|string>
     */
    protected array $handlers = [];
    protected bool $running = false;
    /** @var resource|null */
    protected $serverSocket = null;
    protected ?string $registeredNodeId = null;

    public function __construct(
        protected ?Container $container = null,
        protected ?ServiceRegistryInterface $registry = null
    ) {}

    public function register(string $serviceName, object|string $handler): self
    {
        $this->handlers[$serviceName] = $handler;
        return $this;
    }

    public function hasService(string $serviceName): bool
    {
        return isset($this->handlers[$serviceName]);
    }

    public function getRegisteredServices(): array
    {
        return array_keys($this->handlers);
    }

    /**
     * Execute incoming RPC request payload and generate JSON response.
     */
    public function handle(string $rawPayload): string
    {
        $start = microtime(true);
        $requestId = 'unknown';

        try {
            $request = RpcRequest::fromJson(trim($rawPayload));
            $requestId = $request->id;

            if (empty($request->service) || !isset($this->handlers[$request->service])) {
                throw new RpcException(
                    "Service [{$request->service}] is not registered on this RPC server.",
                    RpcException::METHOD_NOT_FOUND
                );
            }

            $handler = $this->resolveHandler($this->handlers[$request->service]);
            $result = $this->dispatchMethod($handler, $request->method, $request->params);

            $durationMs = round((microtime(true) - $start) * 1000, 3);
            return RpcResponse::success($requestId, $result, $durationMs, $request->metadata)->toJson();
        } catch (RpcException $e) {
            $durationMs = round((microtime(true) - $start) * 1000, 3);
            return RpcResponse::error($requestId, $e->getMessage(), $e->getCode(), $e->data, $durationMs)->toJson();
        } catch (Throwable $e) {
            $durationMs = round((microtime(true) - $start) * 1000, 3);
            return RpcResponse::error(
                $requestId,
                $e->getMessage(),
                RpcException::INTERNAL_ERROR,
                ['exception' => get_class($e), 'file' => $e->getFile(), 'line' => $e->getLine()],
                $durationMs
            )->toJson();
        }
    }

    /**
     * Start the socket listening loop.
     */
    public function serve(string $host = '0.0.0.0', int $port = 9500): void
    {
        $address = "tcp://{$host}:{$port}";
        $context = stream_context_create([
            'socket' => [
                'so_reuseport' => 1,
                'backlog'      => 512,
            ]
        ]);

        $this->serverSocket = @stream_socket_server($address, $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);

        if (!$this->serverSocket) {
            throw new RpcException("Failed to bind RPC Server to {$address}: {$errstr} ({$errno})");
        }

        stream_set_blocking($this->serverSocket, false);
        $this->running = true;

        // Auto-register services with registry if present
        if ($this->registry !== null) {
            foreach ($this->handlers as $serviceName => $handler) {
                $instance = ServiceInstance::create(
                    serviceName: $serviceName,
                    host: $host === '0.0.0.0' ? '127.0.0.1' : $host,
                    port: $port,
                    metadata: ['version' => '1.0.0']
                );
                $this->registeredNodeId = $instance->id;
                $this->registry->register($instance);
            }
        }

        echo " NetFusion RPC Server listening on {$address}\n";
        echo " Registered services: " . implode(', ', array_keys($this->handlers)) . "\n";

        $clients = [];
        $lastHeartbeat = microtime(true);

        while ($this->running) {
            $read = array_merge([$this->serverSocket], $clients);
            $write = null;
            $except = null;

            if (@stream_select($read, $write, $except, 0, 200000) > 0) {
                // New incoming client connection
                if (in_array($this->serverSocket, $read, true)) {
                    $newSocket = @stream_socket_accept($this->serverSocket, 0);
                    if ($newSocket) {
                        stream_set_blocking($newSocket, false);
                        $clients[(int) $newSocket] = $newSocket;
                    }
                    unset($read[array_search($this->serverSocket, $read, true)]);
                }

                // Handle data from clients
                foreach ($read as $clientSocket) {
                    $buffer = '';
                    while (($chunk = @fread($clientSocket, 8192)) !== false && $chunk !== '') {
                        $buffer .= $chunk;
                        if (str_ends_with($chunk, "\n")) {
                            break;
                        }
                    }

                    if ($buffer !== '') {
                        $response = $this->handle($buffer);
                        @fwrite($clientSocket, $response . "\n");
                    }

                    if (feof($clientSocket) || $buffer !== '') {
                        @fclose($clientSocket);
                        unset($clients[(int) $clientSocket]);
                    }
                }
            }

            // Periodic heartbeat to registry
            if ($this->registry !== null && $this->registeredNodeId !== null) {
                if ((microtime(true) - $lastHeartbeat) >= 5.0) {
                    $this->registry->heartbeat($this->registeredNodeId);
                    $lastHeartbeat = microtime(true);
                }
            }
        }

        // Cleanup on stop
        if ($this->serverSocket) {
            @fclose($this->serverSocket);
        }
        if ($this->registry !== null && $this->registeredNodeId !== null) {
            $this->registry->deregister($this->registeredNodeId);
        }
    }

    public function stop(): void
    {
        $this->running = false;
    }

    protected function resolveHandler(object|string $handler): object
    {
        if (is_object($handler)) {
            return $handler;
        }

        if ($this->container !== null) {
            return $this->container->make($handler);
        }

        if (class_exists($handler)) {
            return new $handler();
        }

        throw new RpcException("Handler class [{$handler}] not found.", RpcException::INTERNAL_ERROR);
    }

    protected function dispatchMethod(object $handler, string $method, array $params): mixed
    {
        $ref = new ReflectionClass($handler);

        if (!$ref->hasMethod($method)) {
            throw new RpcException(
                "Method [{$method}] not found on service handler [" . get_class($handler) . "].",
                RpcException::METHOD_NOT_FOUND
            );
        }

        $refMethod = $ref->getMethod($method);
        if (!$refMethod->isPublic()) {
            throw new RpcException(
                "Method [{$method}] on service handler is not public.",
                RpcException::METHOD_NOT_FOUND
            );
        }

        // Match parameters
        $args = $this->resolveArguments($refMethod, $params);
        return $refMethod->invokeArgs($handler, $args);
    }

    protected function resolveArguments(ReflectionMethod $method, array $params): array
    {
        $refParams = $method->getParameters();
        if (empty($refParams)) {
            return [];
        }

        $isSequential = array_is_list($params);
        $args = [];

        foreach ($refParams as $i => $refParam) {
            $name = $refParam->getName();

            if ($isSequential) {
                if (array_key_exists($i, $params)) {
                    $args[] = $params[$i];
                } elseif ($refParam->isDefaultValueAvailable()) {
                    $args[] = $refParam->getDefaultValue();
                } elseif ($refParam->allowsNull()) {
                    $args[] = null;
                } else {
                    throw new RpcException("Missing required positional parameter #{$i} (\${$name}).", RpcException::INVALID_PARAMS);
                }
            } else {
                if (array_key_exists($name, $params)) {
                    $args[] = $params[$name];
                } elseif ($refParam->isDefaultValueAvailable()) {
                    $args[] = $refParam->getDefaultValue();
                } elseif ($refParam->allowsNull()) {
                    $args[] = null;
                } else {
                    throw new RpcException("Missing required parameter [\${$name}].", RpcException::INVALID_PARAMS);
                }
            }
        }

        return $args;
    }
}
