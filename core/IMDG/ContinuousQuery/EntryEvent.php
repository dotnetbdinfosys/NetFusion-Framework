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

namespace NetFusion\Core\IMDG\ContinuousQuery;

class EntryEvent
{
    public const TYPE_ADDED = 'ADDED';
    public const TYPE_UPDATED = 'UPDATED';
    public const TYPE_REMOVED = 'REMOVED';
    public const TYPE_EXPIRED = 'EXPIRED';
    public const TYPE_EVICTED = 'EVICTED';

    protected string $mapName;
    protected string $key;
    protected mixed $value;
    protected mixed $oldValue;
    protected string $eventType;
    protected float $timestamp;

    public function __construct(
        string $mapName,
        string $key,
        mixed $value,
        mixed $oldValue,
        string $eventType,
        ?float $timestamp = null
    ) {
        $this->mapName = $mapName;
        $this->key = $key;
        $this->value = $value;
        $this->oldValue = $oldValue;
        $this->eventType = $eventType;
        $this->timestamp = $timestamp ?? microtime(true);
    }

    public function getMapName(): string
    {
        return $this->mapName;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function getOldValue(): mixed
    {
        return $this->oldValue;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function getTimestamp(): float
    {
        return $this->timestamp;
    }

    public function toArray(): array
    {
        return [
            'mapName' => $this->mapName,
            'key' => $this->key,
            'value' => $this->value,
            'oldValue' => $this->oldValue,
            'eventType' => $this->eventType,
            'timestamp' => $this->timestamp,
        ];
    }
}
