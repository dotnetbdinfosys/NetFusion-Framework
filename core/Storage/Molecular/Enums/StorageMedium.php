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

enum StorageMedium: string
{
    case DNA_OLIGO = 'dna_oligo';
    case HOLOGRAPHIC_CRYSTAL = 'holographic_crystal';
    case HYBRID_COLD_VAULT = 'hybrid_cold_vault';
}
