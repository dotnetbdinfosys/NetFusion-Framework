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

enum NodeType: string
{
    case MESH = 'mesh';
    case LIGHT = 'light';
    case CAMERA = 'camera';
    case GAUSSIAN_SPLAT = 'gaussian_splat';
    case PHYSICS_BODY = 'physics_body';
    case TWIN_SENSOR = 'twin_sensor';
    case GROUP = 'group';
}
