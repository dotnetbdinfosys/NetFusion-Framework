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

enum CredentialType: string
{
    case VERIFIABLE_CREDENTIAL = 'VerifiableCredential';
    case VERIFIABLE_PRESENTATION = 'VerifiablePresentation';
    case STATUS_LIST_2021 = 'StatusList2021Credential';
}
