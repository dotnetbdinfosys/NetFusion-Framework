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
use NetFusion\Core\CEP\Contracts\WindowFunctionInterface;
use NetFusion\Core\CEP\Contracts\WindowInterface;
use NetFusion\Core\CEP\Watermark;

class WindowState
{
    protected WindowAssignerInterface $assigner;
    protected WindowFunctionInterface $function;
    protected ?Watermark $watermark = null;

    /**
     * Structure: [partitionKey => [windowKey => ['window' => WindowInterface, 'events' => EventInterface[]]]]
     * @var array<string, array<string, array{window: WindowInterface, events: array<EventInterface>}>>
     */
    protected array $windows = [];

    /**
     * For CountWindows: buffer of un-fired events per key.
     * @var array<string, array<EventInterface>>
     */
    protected array $countBuffers = [];

    public function __construct(WindowAssignerInterface $assigner, WindowFunctionInterface $function)
    {
        $this->assigner = $assigner;
        $this->function = $function;
    }

    /**
     * Add event to state and return any fired window results.
     *
     * @return array<mixed> Fired window results
     */
    public function addEvent(string $key, EventInterface $event, float $currentTimestamp): array
    {
        $results = [];

        // Check if event is late with respect to current watermark
        if ($this->watermark !== null && $this->assigner->isEventTime()) {
            if ($this->watermark->isLate($event->getTimestamp())) {
                if ($this->watermark->getLateEventStrategy() === Watermark::STRATEGY_DROP) {
                    return [];
                }
            }
        }

        // Handle CountWindow specially
        if ($this->assigner instanceof CountWindow) {
            return $this->handleCountWindow($key, $event);
        }

        // Assign windows
        $assignedWindows = $this->assigner->assignWindows($event, $currentTimestamp);

        // Handle Session Window Merging if applicable
        if ($this->assigner instanceof SessionWindow) {
            $assignedWindows = $this->mergeSessionWindows($key, $assignedWindows[0]);
        }

        if (!isset($this->windows[$key])) {
            $this->windows[$key] = [];
        }

        foreach ($assignedWindows as $window) {
            $wKey = $window->getKey();
            if (!isset($this->windows[$key][$wKey])) {
                $this->windows[$key][$wKey] = [
                    'window' => $window,
                    'events' => [],
                ];
            }

            $this->windows[$key][$wKey]['events'][] = $event;
        }

        return $results;
    }

    /**
     * Advance watermark and fire all expired windows.
     *
     * @return array<mixed>
     */
    public function advanceWatermark(Watermark $watermark): array
    {
        $this->watermark = $watermark;
        $wmTime = $watermark->getTimestamp();
        $results = [];

        foreach ($this->windows as $key => $keyWindows) {
            foreach ($keyWindows as $wKey => $entry) {
                /** @var WindowInterface $window */
                $window = $entry['window'];
                if ($window->isExpired($wmTime)) {
                    $res = $this->function->apply($key, $window, $entry['events']);
                    if ($res !== null) {
                        $results[] = $res;
                    }
                    unset($this->windows[$key][$wKey]);
                }
            }
        }

        return $results;
    }

    /**
     * Force-fire all currently active windows (e.g., at end of batch or stream flush).
     *
     * @return array<mixed>
     */
    public function flush(): array
    {
        $results = [];

        // Flush time windows
        foreach ($this->windows as $key => $keyWindows) {
            foreach ($keyWindows as $wKey => $entry) {
                $res = $this->function->apply($key, $entry['window'], $entry['events']);
                if ($res !== null) {
                    $results[] = $res;
                }
            }
        }
        $this->windows = [];

        // Flush remaining count buffers
        if ($this->assigner instanceof CountWindow) {
            foreach ($this->countBuffers as $key => $events) {
                if (!empty($events)) {
                    $win = new TimeWindow(0, (float) count($events), 'count_flush');
                    $res = $this->function->apply($key, $win, $events);
                    if ($res !== null) {
                        $results[] = $res;
                    }
                }
            }
            $this->countBuffers = [];
        }

        return $results;
    }

    protected function handleCountWindow(string $key, EventInterface $event): array
    {
        /** @var CountWindow $cw */
        $cw = $this->assigner;
        $size = $cw->getSize();
        $slide = $cw->getSlide();

        if (!isset($this->countBuffers[$key])) {
            $this->countBuffers[$key] = [];
        }

        $this->countBuffers[$key][] = $event;
        $results = [];

        while (count($this->countBuffers[$key]) >= $size) {
            $windowEvents = array_slice($this->countBuffers[$key], 0, $size);
            $win = new TimeWindow(0, (float) $size, sprintf('count_%d', $size));
            $res = $this->function->apply($key, $win, $windowEvents);
            if ($res !== null) {
                $results[] = $res;
            }

            // Slide window buffer
            array_splice($this->countBuffers[$key], 0, $slide);
        }

        return $results;
    }

    /**
     * Merge overlapping session windows for a key when a new event arrives.
     *
     * @return array<WindowInterface>
     */
    protected function mergeSessionWindows(string $key, WindowInterface $newWindow): array
    {
        if (!isset($this->windows[$key])) {
            return [$newWindow];
        }

        $mergedStart = $newWindow->getStart();
        $mergedEnd = $newWindow->getEnd();
        $mergedEvents = [];
        $keysToRemove = [];

        foreach ($this->windows[$key] as $wKey => $entry) {
            /** @var WindowInterface $w */
            $w = $entry['window'];
            // Check if intervals overlap or touch
            if (!($w->getEnd() < $mergedStart || $w->getStart() > $mergedEnd)) {
                $mergedStart = min($mergedStart, $w->getStart());
                $mergedEnd = max($mergedEnd, $w->getEnd());
                $mergedEvents = array_merge($mergedEvents, $entry['events']);
                $keysToRemove[] = $wKey;
            }
        }

        foreach ($keysToRemove as $k) {
            unset($this->windows[$key][$k]);
        }

        $mergedWindow = new TimeWindow($mergedStart, $mergedEnd, sprintf('session_%s_%s', (string) $mergedStart, (string) $mergedEnd));
        
        $this->windows[$key][$mergedWindow->getKey()] = [
            'window' => $mergedWindow,
            'events' => $mergedEvents,
        ];

        return [$mergedWindow];
    }

    public function getActiveWindowCount(): int
    {
        $cnt = 0;
        foreach ($this->windows as $keyWindows) {
            $cnt += count($keyWindows);
        }
        return $cnt;
    }

    public function toArray(): array
    {
        $out = [];
        foreach ($this->windows as $key => $keyWindows) {
            $out[$key] = [];
            foreach ($keyWindows as $wKey => $entry) {
                $out[$key][$wKey] = [
                    'window' => $entry['window']->toString(),
                    'event_count' => count($entry['events']),
                ];
            }
        }
        return $out;
    }
}
