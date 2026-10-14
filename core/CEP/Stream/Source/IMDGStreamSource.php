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
use NetFusion\Core\CEP\Event;
use NetFusion\Core\IMDG\Contracts\ContinuousQueryListenerInterface;
use NetFusion\Core\IMDG\ContinuousQuery\EntryEvent;
use NetFusion\Core\IMDG\DistributedMap;

class IMDGStreamSource implements SourceInterface, ContinuousQueryListenerInterface
{
    protected string $name;
    protected ?DistributedMap $map;
    /** @var array<EventInterface> */
    protected array $eventBuffer = [];
    protected ?string $listenerId = null;

    public function __construct(string $name, ?DistributedMap $map = null)
    {
        $this->name = $name;
        $this->map = $map;

        if ($this->map !== null) {
            $this->listenerId = $this->map->addContinuousQueryListener($this);
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function onEvent(EntryEvent $entryEvent): void
    {
        $event = new Event(
            'imdg.' . strtolower($entryEvent->getEventType()),
            [
                'map' => $entryEvent->getMapName(),
                'key' => $entryEvent->getKey(),
                'value' => $entryEvent->getValue(),
                'old_value' => $entryEvent->getOldValue(),
                'type' => $entryEvent->getEventType(),
            ],
            $entryEvent->getTimestamp(),
            null,
            ['source' => 'imdg', 'map' => $entryEvent->getMapName()]
        );

        $this->eventBuffer[] = $event;
    }

    public function onEntryEvent(EntryEvent $entryEvent): void
    {
        $this->onEvent($entryEvent);
    }

    public function push(EventInterface $event): void
    {
        $this->eventBuffer[] = $event;
    }

    public function poll(): array
    {
        $events = $this->eventBuffer;
        $this->eventBuffer = [];
        return $events;
    }

    public function hasNext(): bool
    {
        return !empty($this->eventBuffer);
    }

    public function detach(): void
    {
        if ($this->map !== null && $this->listenerId !== null) {
            $this->map->removeContinuousQueryListener($this->listenerId);
            $this->listenerId = null;
        }
    }
}
