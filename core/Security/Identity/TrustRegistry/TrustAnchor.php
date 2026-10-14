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

class TrustAnchor
{
    public function __construct(
        protected string $ecosystemId,
        protected string $issuerDid,
        protected string $schemaUri,
        protected TrustState $state = TrustState::AUTHORIZED,
        protected ?string $validUntil = null,
        protected ?string $jurisdiction = null
    ) {
    }

    public function getEcosystemId(): string
    {
        return $this->ecosystemId;
    }

    public function getIssuerDid(): string
    {
        return $this->issuerDid;
    }

    public function getSchemaUri(): string
    {
        return $this->schemaUri;
    }

    public function getState(): TrustState
    {
        return $this->state;
    }

    public function setState(TrustState $state): void
    {
        $this->state = $state;
    }

    public function getValidUntil(): ?string
    {
        return $this->validUntil;
    }

    public function getJurisdiction(): ?string
    {
        return $this->jurisdiction;
    }

    public function isAuthorized(): bool
    {
        if ($this->state !== TrustState::AUTHORIZED) {
            return false;
        }

        if ($this->validUntil !== null && strtotime($this->validUntil) < time()) {
            return false;
        }

        return true;
    }

    public function toArray(): array
    {
        return [
            'ecosystemId' => $this->ecosystemId,
            'issuerDid' => $this->issuerDid,
            'schemaUri' => $this->schemaUri,
            'state' => $this->state->value,
            'validUntil' => $this->validUntil,
            'jurisdiction' => $this->jurisdiction,
        ];
    }
}
