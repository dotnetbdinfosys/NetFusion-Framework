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

class SessionWindow implements WindowAssignerInterface
{
    protected float $inactivityGapSeconds;
    protected bool $eventTime;

    public function __construct(float $inactivityGapSeconds, bool $eventTime = true)
    {
        $this->inactivityGapSeconds = max(0.001, $inactivityGapSeconds);
        $this->eventTime = $eventTime;
    }

    public static function withGap(float $seconds, bool $eventTime = true): self
    {
        return new self($seconds, $eventTime);
    }

    public function assignWindows(EventInterface $event, float $timestamp): array
    {
        $effectiveTimestamp = $this->eventTime ? $event->getTimestamp() : $timestamp;
        $start = $effectiveTimestamp;
        $end = $effectiveTimestamp + $this->inactivityGapSeconds;

        return [
            new TimeWindow($start, $end, sprintf('session_%s_%s', (string) $start, (string) $end))
        ];
    }

    public function isEventTime(): bool
    {
        return $this->eventTime;
    }

    public function getInactivityGapSeconds(): float
    {
        return $this->inactivityGapSeconds;
    }

    public function getType(): string
    {
        return 'SessionWindow';
    }
}
