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

namespace NetFusion\Core\CEP\Window;

use NetFusion\Core\CEP\Contracts\EventInterface;
use NetFusion\Core\CEP\Contracts\WindowAssignerInterface;
use NetFusion\Core\CEP\Contracts\WindowInterface;

class TumblingTimeWindow implements WindowAssignerInterface
{
    protected float $sizeSeconds;
    protected float $offsetSeconds;
    protected bool $eventTime;

    public function __construct(float $sizeSeconds, float $offsetSeconds = 0.0, bool $eventTime = true)
    {
        $this->sizeSeconds = max(0.001, $sizeSeconds);
        $this->offsetSeconds = $offsetSeconds;
        $this->eventTime = $eventTime;
    }

    public static function of(float $sizeSeconds, float $offsetSeconds = 0.0, bool $eventTime = true): self
    {
        return new self($sizeSeconds, $offsetSeconds, $eventTime);
    }

    public static function seconds(int|float $seconds): self
    {
        return new self((float) $seconds);
    }

    public static function minutes(int|float $minutes): self
    {
        return new self((float) ($minutes * 60));
    }

    public static function hours(int|float $hours): self
    {
        return new self((float) ($hours * 3600));
    }

    public function assignWindows(EventInterface $event, float $timestamp): array
    {
        $effectiveTimestamp = $this->eventTime ? $event->getTimestamp() : $timestamp;
        $start = floor(($effectiveTimestamp - $this->offsetSeconds) / $this->sizeSeconds) * $this->sizeSeconds + $this->offsetSeconds;
        $end = $start + $this->sizeSeconds;

        return [
            new TimeWindow($start, $end, sprintf('tumbling_%s_%s', (string) $start, (string) $end))
        ];
    }

    public function isEventTime(): bool
    {
        return $this->eventTime;
    }

    public function getSizeSeconds(): float
    {
        return $this->sizeSeconds;
    }

    public function getType(): string
    {
        return 'TumblingTimeWindow';
    }
}
