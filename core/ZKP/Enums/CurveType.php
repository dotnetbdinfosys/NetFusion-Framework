<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\ZKP\Enums;

enum CurveType: string
{
    case BN254       = 'bn254';
    case BABY_JUBJUB = 'baby_jubjub';
    case SECP256K1   = 'secp256k1';
}
