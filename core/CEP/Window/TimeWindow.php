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

use JsonSerializable;
use NetFusion\Core\CEP\Contracts\WindowInterface;

class TimeWindow implements WindowInterface, JsonSerializable
{
    protected float $start;
    protected float $end;
    protected string $key;

    public function __construct(float $start, float $end, ?string $key = null)
    {
        $this->start = $start;
        $this->end = $end;
        $this->key = $key ?? sprintf('window_%s_%s', (string) $start, (string) $end);
    }

    public static function of(float $start, float $end, ?string $key = null): self
    {
        return new self($start, $end, $key);
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getStart(): float
    {
        return $this->start;
    }

    public function getEnd(): float
    {
        return $this->end;
    }

    public function contains(float $timestamp): bool
    {
        return $timestamp >= $this->start && $timestamp < $this->end;
    }

    public function isExpired(float $watermark): bool
    {
        return $watermark >= $this->end;
    }

    public function getDuration(): float
    {
        return $this->end - $this->start;
    }

    public function toString(): string
    {
        return sprintf('[%s, %s)', date('Y-m-d H:i:s', (int) $this->start), date('Y-m-d H:i:s', (int) $this->end));
    }

    public function jsonSerialize(): array
    {
        return [
            'key' => $this->key,
            'start' => $this->start,
            'end' => $this->end,
            'start_formatted' => date('Y-m-d H:i:s', (int) $this->start),
            'end_formatted' => date('Y-m-d H:i:s', (int) $this->end),
        ];
    }
}
