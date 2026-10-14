<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/AI/Neuromorphic/Enums/EncodingScheme.php
 | Author    : Md. Atiquzzaman Ashish
 | Company   : DotNetBD
 | Website   : www.dotnetbd.com
 | Copyright : © DotNetBD. All rights reserved.
 | License   : MIT
 | Version   : 1.0.0
 |
 |--------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace NetFusion\Core\AI\Neuromorphic\Enums;

/**
 * Temporal neural spike encoding algorithms.
 */
enum EncodingScheme: string
{
    case RATE       = 'rate';       // Frequency-based Poisson or deterministic spike train
    case TTFS       = 'ttfs';       // Time-to-First-Spike (Latency coding)
    case DELTA      = 'delta';      // Event-based change detector (Temporal change spike)
    case POPULATION = 'population'; // Gaussian receptive field population encoding

    public function description(): string
    {
        return match ($this) {
            self::RATE       => 'Continuous intensity mapped to stochastic or uniform firing frequency',
            self::TTFS       => 'Continuous magnitude mapped logarithmically to earliest latency spike arrival',
            self::DELTA      => 'Asynchronous spike generated only when absolute signal delta exceeds threshold',
            self::POPULATION => 'Distributed activation across overlapping Gaussian receptive field neurons',
        };
    }
}
