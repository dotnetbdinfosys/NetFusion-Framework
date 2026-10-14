<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\CEP\Stream\Sink;

use NetFusion\Core\CEP\Contracts\SinkInterface;

class EventBusStreamSink implements SinkInterface
{
    protected string $name;
    protected string $eventName;
    /** @var callable|null */
    protected $eventDispatcher;

    public function __construct(string $name, string $eventName, ?callable $eventDispatcher = null)
    {
        $this->name = $name;
        $this->eventName = $eventName;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function emit(mixed $data, array $context = []): void
    {
        if ($this->eventDispatcher !== null) {
            call_user_func($this->eventDispatcher, $this->eventName, $data, $context);
        }
    }
}
