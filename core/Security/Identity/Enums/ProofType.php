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

enum ProofType: string
{
    case ED25519_2020 = 'Ed25519Signature2020';
    case ML_DSA_44 = 'PostQuantumMlDsa44Signature2026';
    case BBS_PLUS = 'BbsBlsSignature2020';
    case BBS_PLUS_PROOF = 'BbsBlsSignatureProof2020';
}
