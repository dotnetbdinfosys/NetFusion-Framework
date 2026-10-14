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

class ArrayStreamSource implements SourceInterface
{
    protected string $name;
    /** @var array<EventInterface> */
    protected array $events = [];
    protected int $cursor = 0;

    /**
     * @param string $name
     * @param array<EventInterface> $initialEvents
     */
    public function __construct(string $name = 'array_source', array $initialEvents = [])
    {
        $this->name = $name;
        $this->events = $initialEvents;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function push(EventInterface $event): void
    {
        $this->events[] = $event;
    }

    public function poll(): array
    {
        if ($this->cursor >= count($this->events)) {
            return [];
        }

        $slice = array_slice($this->events, $this->cursor);
        $this->cursor = count($this->events);
        return $slice;
    }

    public function hasNext(): bool
    {
        return $this->cursor < count($this->events);
    }

    public function reset(): void
    {
        $this->cursor = 0;
    }

    public function count(): int
    {
        return count($this->events);
    }
}
