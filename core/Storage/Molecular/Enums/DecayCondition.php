<?php

declare(strict_types=1);

/**
 * NetFusion Framework - Enterprise Holographic Storage & DNA Molecular Data Encoding
 *
 * @author  Md. Atiquzzaman Ashish <atiquzzaman.ashish@dotnetbd.com>
 * @company DotNetBD
 * @link    https://www.dotnetbd.com
 * @license MIT
 */

namespace NetFusion\Core\Storage\Molecular\Enums;

enum DecayCondition: string
{
    case CRYOGENIC = 'cryogenic';                 // Liquid nitrogen (-196°C) - millions of years
    case ROOM_TEMPERATURE = 'room_temperature';   // Ambient (20°C) - hundreds of years
    case ACCELERATED_HEAT = 'accelerated_heat';   // Accelerated aging stress test (70°C)
}
