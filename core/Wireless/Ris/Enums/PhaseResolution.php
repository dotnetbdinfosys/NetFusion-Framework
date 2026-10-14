<?php
/**
 * NetFusion Framework - Enterprise 6G Wireless RIS Engine
 *
 * Phase shift quantization resolution for metamaterial elements.
 *
 * @package    NetFusion\Wireless\Ris\Enums
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Wireless\Ris\Enums;

enum PhaseResolution: string
{
    case Continuous = 'continuous';
    case OneBit     = '1_bit';    // 2 states: {0, pi}
    case TwoBit     = '2_bit';    // 4 states: {0, pi/2, pi, 3pi/2}
    case ThreeBit   = '3_bit';    // 8 states: 2*pi*k / 8

    /**
     * Quantize an ideal continuous phase shift theta in [0, 2pi) to the discrete state.
     */
    public function quantize(float $phaseRadians): float
    {
        // Normalize to [0, 2pi)
        $twoPi = 2.0 * M_PI;
        $phase = fmod($phaseRadians, $twoPi);
        if ($phase < 0) {
            $phase += $twoPi;
        }

        if ($this === self::Continuous) {
            return $phase;
        }

        $levels = match ($this) {
            self::OneBit   => 2,
            self::TwoBit   => 4,
            self::ThreeBit => 8,
            default        => 4,
        };

        $step = $twoPi / $levels;
        $levelIndex = (int) round($phase / $step) % $levels;
        return $levelIndex * $step;
    }

    /**
     * Number of quantization levels.
     */
    public function discreteLevels(): int
    {
        return match ($this) {
            self::Continuous => PHP_INT_MAX,
            self::OneBit     => 2,
            self::TwoBit     => 4,
            self::ThreeBit   => 8,
        };
    }
}

