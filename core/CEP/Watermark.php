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

namespace NetFusion\Core\CEP;

class Watermark
{
    public const STRATEGY_DROP = 'drop';
    public const STRATEGY_SIDE_OUTPUT = 'side_output';
    public const STRATEGY_ALLOWED_LATENESS = 'allowed_lateness';

    protected float $timestamp;
    protected float $allowedLatenessSeconds;
    protected string $lateEventStrategy;

    public function __construct(
        float $timestamp,
        float $allowedLatenessSeconds = 0.0,
        string $lateEventStrategy = self::STRATEGY_DROP
    ) {
        $this->timestamp = $timestamp;
        $this->allowedLatenessSeconds = $allowedLatenessSeconds;
        $this->lateEventStrategy = $lateEventStrategy;
    }

    public static function of(
        float $timestamp,
        float $allowedLatenessSeconds = 0.0,
        string $lateEventStrategy = self::STRATEGY_DROP
    ): self {
        return new self($timestamp, $allowedLatenessSeconds, $lateEventStrategy);
    }

    public function getTimestamp(): float
    {
        return $this->timestamp;
    }

    public function getAllowedLatenessSeconds(): float
    {
        return $this->allowedLatenessSeconds;
    }

    public function getLateEventStrategy(): string
    {
        return $this->lateEventStrategy;
    }

    /**
     * Check if an event timestamp is considered late.
     */
    public function isLate(float $eventTimestamp): bool
    {
        return $eventTimestamp < ($this->timestamp - $this->allowedLatenessSeconds);
    }
}
