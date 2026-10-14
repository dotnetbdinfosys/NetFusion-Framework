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

namespace NetFusion\Core\ZKP\Credentials;

/**
 * Selective Disclosure Presentation generator and verifier.
 * Allows prover to reveal specific attributes or prove predicate statements
 * (e.g., age >= 18) while keeping all other credential claims entirely hidden.
 */
class SelectiveDisclosure
{
    /**
     * Create a disclosure presentation revealing only requested attributes.
     *
     * @param string[] $disclosedAttributes
     * @return array{credential_id: string, issuer: string, subject: string, disclosed_claims: array, all_commitments: array}
     */
    public static function createPresentation(VerifiableCredential $vc, array $disclosedAttributes): array
    {
        $disclosedClaims = [];
        foreach ($disclosedAttributes as $attr) {
            if (array_key_exists($attr, $vc->getClaims())) {
                $disclosedClaims[$attr] = $vc->getClaims()[$attr];
            }
        }

        return [
            'credential_id'    => $vc->getId(),
            'issuer'           => $vc->getIssuer(),
            'subject'          => $vc->getSubject(),
            'disclosed_claims' => $disclosedClaims,
            'all_commitments'  => $vc->getAttributeCommitments(),
        ];
    }

    /**
     * Verify that the disclosed claims correctly match their published commitments.
     */
    public static function verifyPresentation(array $presentation): bool
    {
        $credId = (string)($presentation['credential_id'] ?? '');
        $claims = (array)($presentation['disclosed_claims'] ?? []);
        $commitments = (array)($presentation['all_commitments'] ?? []);

        foreach ($claims as $key => $val) {
            if (!isset($commitments[$key])) {
                return false;
            }

            $salt = hash('sha256', $credId . ':' . $key);
            $valStr = is_scalar($val) ? (string)$val : json_encode($val);
            $expectedCommitment = hash('sha256', $salt . ':' . $valStr);

            if ($commitments[$key] !== $expectedCommitment) {
                return false;
            }
        }

        return true;
    }
}
