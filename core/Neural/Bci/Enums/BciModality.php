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

enum BciModality: string
{
    case MOTOR_IMAGERY         = 'motor_imagery';
    case SSVEP                 = 'ssvep';
    case P300_EVENT_RELATED    = 'p300_erp';
    case PASSIVE_NEUROFEEDBACK = 'passive_neurofeedback';
}
