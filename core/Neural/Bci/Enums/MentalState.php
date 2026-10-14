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

enum MentalState: string
{
    case DEEP_RELAXATION = 'deep_relaxation';
    case CALM_ALERT      = 'calm_alert';
    case HIGH_FOCUS      = 'high_focus';
    case FATIGUED        = 'fatigued';
    case MOTOR_INTENT    = 'motor_intent';
}
