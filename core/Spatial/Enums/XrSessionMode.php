<?php

declare(strict_types=1);

/**
 * NetFusion Framework - Enterprise Spatial Computing & WebXR Hyper-Spatial Digital Twin
 *
 * @author  Md. Atiquzzaman Ashish <atiquzzaman.ashish@dotnetbd.com>
 * @company DotNetBD
 * @link    https://www.dotnetbd.com
 * @license MIT
 */

namespace NetFusion\Core\Spatial\Enums;

enum XrSessionMode: string
{
    case IMMERSIVE_VR = 'immersive-vr';
    case IMMERSIVE_AR = 'immersive-ar';
    case INLINE = 'inline';
}
