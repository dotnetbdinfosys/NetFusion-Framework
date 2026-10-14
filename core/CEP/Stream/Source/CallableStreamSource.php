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

namespace NetFusion\Core\CEP\Stream\Source;

use NetFusion\Core\CEP\Contracts\EventInterface;
use NetFusion\Core\CEP\Contracts\SourceInterface;

class CallableStreamSource implements SourceInterface
{
    protected string $name;
    /** @var callable */
    protected $generator;
    /** @var array<EventInterface> */
    protected array $buffer = [];

    public function __construct(string $name, callable $generator)
    {
        $this->name = $name;
        $this->generator = $generator;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function push(EventInterface $event): void
    {
        $this->buffer[] = $event;
    }

    public function poll(): array
    {
        $generated = call_user_func($this->generator);
        $events = [];

        if (is_array($generated)) {
            $events = array_merge($this->buffer, $generated);
        } elseif ($generated instanceof EventInterface) {
            $events = array_merge($this->buffer, [$generated]);
        } else {
            $events = $this->buffer;
        }

        $this->buffer = [];
        return $events;
    }

    public function hasNext(): bool
    {
        return true;
    }
}
