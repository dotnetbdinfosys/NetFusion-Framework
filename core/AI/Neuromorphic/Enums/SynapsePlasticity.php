<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/AI/Neuromorphic/Enums/SynapsePlasticity.php
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
 * Synaptic plasticity adaptation rules.
 */
enum SynapsePlasticity: string
{
    case STATIC_WEIGHT = 'static';     // Static fixed synaptic weight
    case STDP          = 'stdp';       // Spike-Timing-Dependent Plasticity (Hebbian)
    case ANTI_STDP     = 'anti_stdp';  // Anti-Hebbian Negative Plasticity
    case TRIPLET_STDP  = 'triplet';    // High-order Triplet STDP for dense frequency bursts
}
