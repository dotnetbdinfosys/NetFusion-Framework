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

namespace NetFusion\Core\CEP\Pattern;

use JsonSerializable;
use NetFusion\Core\CEP\Contracts\EventInterface;

class MatchedPattern implements JsonSerializable
{
    protected string $patternName;
    protected string $partitionKey;
    /** @var array<string, array<EventInterface>> */
    protected array $events = [];
    protected float $startTimestamp;
    protected float $endTimestamp;

    /**
     * @param string $patternName
     * @param string $partitionKey
     * @param array<string, array<EventInterface>> $events
     * @param float $startTimestamp
     * @param float $endTimestamp
     */
    public function __construct(
        string $patternName,
        string $partitionKey,
        array $events,
        float $startTimestamp,
        float $endTimestamp
    ) {
        $this->patternName = $patternName;
        $this->partitionKey = $partitionKey;
        $this->events = $events;
        $this->startTimestamp = $startTimestamp;
        $this->endTimestamp = $endTimestamp;
    }

    public function getPatternName(): string
    {
        return $this->patternName;
    }

    public function getPartitionKey(): string
    {
        return $this->partitionKey;
    }

    /**
     * Get first matched event for a pattern node.
     */
    public function get(string $nodeName): ?EventInterface
    {
        if (isset($this->events[$nodeName]) && !empty($this->events[$nodeName])) {
            return $this->events[$nodeName][0];
        }
        return null;
    }

    /**
     * Get all matched events for a pattern node (e.g. for times() or oneOrMore()).
     *
     * @return array<EventInterface>
     */
    public function getAll(string $nodeName): array
    {
        return $this->events[$nodeName] ?? [];
    }

    /**
     * Get entire matched map of pattern nodes to events.
     *
     * @return array<string, array<EventInterface>>
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    public function getStartTimestamp(): float
    {
        return $this->startTimestamp;
    }

    public function getEndTimestamp(): float
    {
        return $this->endTimestamp;
    }

    public function getDuration(): float
    {
        return max(0.0, $this->endTimestamp - $this->startTimestamp);
    }

    public function toArray(): array
    {
        $serializedEvents = [];
        foreach ($this->events as $node => $nodeEvents) {
            $serializedEvents[$node] = array_map(
                fn(EventInterface $e) => $e->toArray(),
                $nodeEvents
            );
        }

        return [
            'pattern_name' => $this->patternName,
            'partition_key' => $this->partitionKey,
            'duration' => $this->getDuration(),
            'start_timestamp' => $this->startTimestamp,
            'end_timestamp' => $this->endTimestamp,
            'events' => $serializedEvents,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
