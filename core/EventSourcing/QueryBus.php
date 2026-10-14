<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/EventSourcing/QueryBus.php
| Module    : EventSourcing
| Namespace : NetFusion\Core\EventSourcing
| Purpose   : CQRS Query Bus with handler registration and execution.
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
use NetFusion\Core\EventSourcing\Contracts\QueryHandlerInterface;
use NetFusion\Core\EventSourcing\Contracts\QueryInterface;
use NetFusion\Core\EventSourcing\Exceptions\HandlerNotFoundException;

class QueryBus
{
    /**
     * @var array<string, QueryHandlerInterface|Closure|string>
     */
    protected array $handlers = [];

    /**
     * @var array<Closure>
     */
    protected array $middleware = [];

    public function register(string $queryClass, QueryHandlerInterface|Closure|string $handler): self
    {
        $this->handlers[$queryClass] = $handler;
        return $this;
    }

    public function pipe(Closure $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    public function ask(QueryInterface $query): mixed
    {
        $queryClass = get_class($query);

        if (!isset($this->handlers[$queryClass])) {
            throw HandlerNotFoundException::forQuery($queryClass);
        }

        $handler = $this->handlers[$queryClass];

        $runner = function (QueryInterface $q) use ($handler): mixed {
            if ($handler instanceof Closure) {
                return $handler($q);
            }

            if (is_string($handler)) {
                if (function_exists('app')) {
                    $instance = app($handler);
                } else {
                    $instance = new $handler();
                }
                return $instance->handle($q);
            }

            return $handler->handle($q);
        };

        $pipeline = array_reduce(
            array_reverse($this->middleware),
            function ($next, $middleware) {
                return function ($q) use ($next, $middleware) {
                    return $middleware($q, $next);
                };
            },
            $runner
        );

        return $pipeline($query);
    }
}