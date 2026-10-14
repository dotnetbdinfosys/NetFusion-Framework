<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/EventSourcing/CommandBus.php
| Module    : EventSourcing
| Namespace : NetFusion\Core\EventSourcing
| Purpose   : CQRS Command Bus with handler registration and middleware pipeline.
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

namespace NetFusion\Core\EventSourcing;

use Closure;
use NetFusion\Core\EventSourcing\Contracts\CommandHandlerInterface;
use NetFusion\Core\EventSourcing\Contracts\CommandInterface;
use NetFusion\Core\EventSourcing\Exceptions\HandlerNotFoundException;

class CommandBus
{
    /**
     * @var array<string, CommandHandlerInterface|Closure|string>
     */
    protected array $handlers = [];

    /**
     * @var array<Closure>
     */
    protected array $middleware = [];

    public function register(string $commandClass, CommandHandlerInterface|Closure|string $handler): self
    {
        $this->handlers[$commandClass] = $handler;
        return $this;
    }

    public function pipe(Closure $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    public function dispatch(CommandInterface $command): mixed
    {
        $commandClass = get_class($command);

        if (!isset($this->handlers[$commandClass])) {
            throw HandlerNotFoundException::forCommand($commandClass);
        }

        $handler = $this->handlers[$commandClass];

        $runner = function (CommandInterface $cmd) use ($handler): mixed {
            if ($handler instanceof Closure) {
                return $handler($cmd);
            }

            if (is_string($handler)) {
                if (function_exists('app')) {
                    $instance = app($handler);
                } else {
                    $instance = new $handler();
                }
                return $instance->handle($cmd);
            }

            return $handler->handle($cmd);
        };

        // Run through middleware pipeline
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            function ($next, $middleware) {
                return function ($cmd) use ($next, $middleware) {
                    return $middleware($cmd, $next);
                };
            },
            $runner
        );

        return $pipeline($command);
    }
}