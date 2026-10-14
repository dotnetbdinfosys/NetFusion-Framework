<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Privacy/Differential/Enums/MechanismType.php
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

namespace NetFusion\Core\Privacy\Differential\Enums;

/**
 * Differential Privacy Noise Mechanism Types.
 */
enum MechanismType: string
{
    case LAPLACE             = 'laplace';
    case GAUSSIAN            = 'gaussian';
    case EXPONENTIAL         = 'exponential';
    case RANDOMIZED_RESPONSE = 'randomized_response';

    public function description(): string
    {
        return match ($this) {
            self::LAPLACE             => 'Laplace Mechanism (Pure Epsilon-DP with L1 Sensitivity)',
            self::GAUSSIAN            => 'Gaussian Mechanism (Approximate (Epsilon, Delta)-DP with L2 Sensitivity)',
            self::EXPONENTIAL         => 'Exponential Mechanism (Non-numeric selections with utility functions)',
            self::RANDOMIZED_RESPONSE => 'Local Differential Privacy (Randomized response coin-flipping)',
        };
    }
}
