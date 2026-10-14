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

namespace NetFusion\Core\Security\Identity\TrustRegistry;

use NetFusion\Core\Security\Identity\Enums\TrustState;

class TrustRegistryEngine
{
    /** @var array<string, TrustAnchor> */
    protected array $anchors = [];

    /**
     * Register or update a Trust Anchor
     */
    public function registerTrustAnchor(TrustAnchor $anchor): void
    {
        $key = $this->composeKey($anchor->getEcosystemId(), $anchor->getIssuerDid(), $anchor->getSchemaUri());
        $this->anchors[$key] = $anchor;
    }

    /**
     * Lookup Trust Anchor
     */
    public function getTrustAnchor(string $ecosystemId, string $issuerDid, string $schemaUri): ?TrustAnchor
    {
        $key = $this->composeKey($ecosystemId, $issuerDid, $schemaUri);
        return $this->anchors[$key] ?? null;
    }

    /**
     * Verify whether an issuer is officially authorized to issue a specific credential schema
     */
    public function verifyIssuerAuthorization(string $ecosystemId, string $issuerDid, string $schemaUri): bool
    {
        $anchor = $this->getTrustAnchor($ecosystemId, $issuerDid, $schemaUri);
        if ($anchor === null) {
            return false;
        }

        return $anchor->isAuthorized();
    }

    /**
     * Revoke or suspend a trust anchor
     */
    public function revokeTrustAnchor(string $ecosystemId, string $issuerDid, string $schemaUri): void
    {
        $anchor = $this->getTrustAnchor($ecosystemId, $issuerDid, $schemaUri);
        if ($anchor !== null) {
            $anchor->setState(TrustState::REVOKED);
        }
    }

    /**
     * Return all registered trust anchors
     *
     * @return TrustAnchor[]
     */
    public function getAllAnchors(): array
    {
        return array_values($this->anchors);
    }

    protected function composeKey(string $ecosystemId, string $issuerDid, string $schemaUri): string
    {
        return "{$ecosystemId}#{$issuerDid}#{$schemaUri}";
    }
}
