<?php

declare(strict_types=1);

/**
 * NetFusion Framework - Enterprise Post-Quantum Decentralized Identity
 *
 * @author  Md. Atiquzzaman Ashish <atiquzzaman.ashish@dotnetbd.com>
 * @company DotNetBD
 * @link    https://www.dotnetbd.com
 * @license MIT
 */

namespace NetFusion\Core\Security\Identity\Enums;

enum TrustState: string
{
    case AUTHORIZED = 'authorized';
    case UNAUTHORIZED = 'unauthorized';
    case REVOKED = 'revoked';
    case SUSPENDED = 'suspended';
}
