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

class CountWindow implements WindowAssignerInterface
{
    protected int $size;
    protected int $slide;

    public function __construct(int $size, ?int $slide = null)
    {
        $this->size = max(1, $size);
        $this->slide = max(1, $slide ?? $size);
    }

    public static function of(int $size, ?int $slide = null): self
    {
        return new self($size, $slide);
    }

    public static function tumbling(int $size): self
    {
        return new self($size, $size);
    }

    public static function sliding(int $size, int $slide): self
    {
        return new self($size, $slide);
    }

    public function assignWindows(EventInterface $event, float $timestamp): array
    {
        // For count windows, TimeWindow interval represents count sequence boundary markers
        return [
            new TimeWindow(0.0, (float) $this->size, sprintf('count_%d_%d', $this->size, $this->slide))
        ];
    }

    public function isEventTime(): bool
    {
        return false;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getSlide(): int
    {
        return $this->slide;
    }

    public function isSliding(): bool
    {
        return $this->slide < $this->size;
    }

    public function getType(): string
    {
        return 'CountWindow';
    }
}
