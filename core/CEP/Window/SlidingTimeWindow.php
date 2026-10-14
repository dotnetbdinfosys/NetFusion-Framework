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

class SlidingTimeWindow implements WindowAssignerInterface
{
    protected float $sizeSeconds;
    protected float $slideSeconds;
    protected float $offsetSeconds;
    protected bool $eventTime;

    public function __construct(
        float $sizeSeconds,
        float $slideSeconds,
        float $offsetSeconds = 0.0,
        bool $eventTime = true
    ) {
        $this->sizeSeconds = max(0.001, $sizeSeconds);
        $this->slideSeconds = max(0.001, $slideSeconds);
        $this->offsetSeconds = $offsetSeconds;
        $this->eventTime = $eventTime;
    }

    public static function of(
        float $sizeSeconds,
        float $slideSeconds,
        float $offsetSeconds = 0.0,
        bool $eventTime = true
    ): self {
        return new self($sizeSeconds, $slideSeconds, $offsetSeconds, $eventTime);
    }

    public function assignWindows(EventInterface $event, float $timestamp): array
    {
        $effectiveTimestamp = $this->eventTime ? $event->getTimestamp() : $timestamp;
        $windows = [];

        // Find the earliest window start that could contain this timestamp:
        // start <= effectiveTimestamp < start + sizeSeconds => start > effectiveTimestamp - sizeSeconds
        $lastStart = floor(($effectiveTimestamp - $this->offsetSeconds) / $this->slideSeconds) * $this->slideSeconds + $this->offsetSeconds;
        
        for ($start = $lastStart; $start + $this->sizeSeconds > $effectiveTimestamp; $start -= $this->slideSeconds) {
            if ($start <= $effectiveTimestamp) {
                $end = $start + $this->sizeSeconds;
                $windows[] = new TimeWindow($start, $end, sprintf('sliding_%s_%s', (string) $start, (string) $end));
            }
        }

        return array_reverse($windows);
    }

    public function isEventTime(): bool
    {
        return $this->eventTime;
    }

    public function getSizeSeconds(): float
    {
        return $this->sizeSeconds;
    }

    public function getSlideSeconds(): float
    {
        return $this->slideSeconds;
    }

    public function getType(): string
    {
        return 'SlidingTimeWindow';
    }
}
