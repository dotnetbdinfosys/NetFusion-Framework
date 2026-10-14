<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/AI/Neuromorphic/Enums/NeuronModel.php
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
 * Supported biophysical and computational neuron models.
 */
enum NeuronModel: string
{
    case LIF           = 'lif';            // Leaky Integrate-and-Fire
    case IZHIKEVICH    = 'izhikevich';     // Izhikevich Dynamical Polychronous Neuron
    case ADAPTIVE_LIF  = 'adaptive_lif';   // Adaptive Threshold LIF

    public function description(): string
    {
        return match ($this) {
            self::LIF          => 'Standard Leaky Integrate-and-Fire with exponential potential decay',
            self::IZHIKEVICH   => 'Two-variable differential dynamical model capable of rich cortical spiking patterns',
            self::ADAPTIVE_LIF => 'Leaky Integrate-and-Fire with dynamic spike-frequency adaptation threshold',
        };
    }
}
