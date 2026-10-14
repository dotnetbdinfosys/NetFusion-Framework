<?php

declare(strict_types=1);

/**
 * NetFusion Framework - Enterprise Brain-Computer Interface (BCI) & Neural Signal Decoding
 *
 * @author  Md. Atiquzzaman Ashish <atiquzzaman.ashish@dotnetbd.com>
 * @company DotNetBD
 * @link    https://www.dotnetbd.com
 * @license MIT
 */

namespace NetFusion\Core\Neural\Bci\Enums;

enum BrainWaveBand: string
{
    case DELTA = 'delta';
    case THETA = 'theta';
    case ALPHA = 'alpha';
    case BETA  = 'beta';
    case GAMMA = 'gamma';

    public function minFrequency(): float
    {
        return match ($this) {
            self::DELTA => 0.5,
            self::THETA => 4.0,
            self::ALPHA => 8.0,
            self::BETA  => 13.0,
            self::GAMMA => 30.0,
        };
    }

    public function maxFrequency(): float
    {
        return match ($this) {
            self::DELTA => 4.0,
            self::THETA => 8.0,
            self::ALPHA => 13.0,
            self::BETA  => 30.0,
            self::GAMMA => 100.0,
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::DELTA => 'Deep dreamless sleep, unconscious somatic repair (0.5 - 4 Hz)',
            self::THETA => 'Deep meditation, memory consolidation, twilight drowsiness (4 - 8 Hz)',
            self::ALPHA => 'Relaxed wakefulness, mindful calm, sensorimotor idling (8 - 13 Hz)',
            self::BETA  => 'Active analytical focus, alert problem-solving, cognitive load (13 - 30 Hz)',
            self::GAMMA => 'Cross-modal information binding, peak focus, high-level cognition (30 - 100 Hz)',
        };
    }
}
