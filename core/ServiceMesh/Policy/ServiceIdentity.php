<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | Author    : Md. Atiquzzaman Ashish
 | Company   : DotNetBD
 | Website   : www.dotnetbd.com
 | Copyright : © DotNetBD. All rights reserved.
 | License   : MIT
 | Version   : 1.0.0
 |
 |--------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace NetFusion\Core\ServiceMesh\Policy;

use NetFusion\Core\ServiceMesh\Spiffe\SpiffeId;

/**
 * Class ServiceIdentity
 *
 * Represents an authenticated cryptographic identity of a service principal.
 */
class ServiceIdentity
{
    public function __construct(
        private readonly SpiffeId $spiffeId,
        private readonly array $claims = [],
        private readonly string $authMethod = 'mtls' // 'mtls' | 'jwt_svid'
    ) {}

    public static function fromSpiffe(string|SpiffeId $spiffeId, string $authMethod = 'mtls'): self
    {
        $id = $spiffeId instanceof SpiffeId ? $spiffeId : new SpiffeId($spiffeId);
        return new self($id, [], $authMethod);
    }

    public function getSpiffeId(): SpiffeId
    {
        return $this->spiffeId;
    }

    public function getServiceName(): string
    {
        return $this->spiffeId->getWorkloadName();
    }

    public function getNamespace(): ?string
    {
        return $this->spiffeId->getNamespace();
    }

    public function getTrustDomain(): string
    {
        return $this->spiffeId->getTrustDomain();
    }

    public function getAuthMethod(): string
    {
        return $this->authMethod;
    }

    public function getClaims(): array
    {
        return $this->claims;
    }

    public function matches(string|SpiffeId $pattern): bool
    {
        return $this->spiffeId->matches($pattern);
    }

    public function __toString(): string
    {
        return (string) $this->spiffeId;
    }
}
