<?php

declare(strict_types=1);

/**
 * NetFusion Framework - Enterprise Optical Photonic Computing & Tensor Light-Interferometer
 *
 * @author  Md. Atiquzzaman Ashish <atiquzzaman.ashish@dotnetbd.com>
 * @company DotNetBD
 * @link    https://www.dotnetbd.com
 * @license MIT
 */

namespace NetFusion\Core\Photonic\Enums;

enum OpticalActivation: string
{
    case LINEAR              = 'linear';
    case SATURABLE_ABSORBER  = 'saturable_absorber';
    case ELECTRO_OPTIC_RELU  = 'eo_relu';
    case OPTICAL_SIGMOID     = 'optical_sigmoid';
}
