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

namespace NetFusion\Core\ServiceMesh\Security;

use JsonSerializable;

class SecurityContext implements JsonSerializable
{
    protected ?SpiffeId $peerSpiffeId = null;
    protected ?string $peerIp = null;
    protected bool $mtlsAuthenticated = false;
    protected ?string $tlsCipher = null;
    /** @var array<string, mixed> */
    protected array $jwtClaims = [];

    public function __construct(
        ?SpiffeId $peerSpiffeId = null,
        ?string $peerIp = null,
        bool $mtlsAuthenticated = false,
        ?string $tlsCipher = 'TLS_AES_256_GCM_SHA384',
        array $jwtClaims = []
    ) {
        $this->peerSpiffeId = $peerSpiffeId;
        $this->peerIp = $peerIp;
        $this->mtlsAuthenticated = $mtlsAuthenticated;
        $this->tlsCipher = $tlsCipher;
        $this->jwtClaims = $jwtClaims;
    }

    public static function authenticated(SpiffeId $spiffeId, ?string $peerIp = null, array $claims = []): self
    {
        return new self($spiffeId, $peerIp, true, 'TLS_AES_256_GCM_SHA384', $claims);
    }

    public static function anonymous(?string $peerIp = null): self
    {
        return new self(null, $peerIp, false);
    }

    public function getPeerSpiffeId(): ?SpiffeId
    {
        return $this->peerSpiffeId;
    }

    public function getPeerIp(): ?string
    {
        return $this->peerIp;
    }

    public function isMtlsAuthenticated(): bool
    {
        return $this->mtlsAuthenticated;
    }

    public function getTlsCipher(): ?string
    {
        return $this->tlsCipher;
    }

    public function getJwtClaims(): array
    {
        return $this->jwtClaims;
    }

    public function getClaim(string $key, mixed $default = null): mixed
    {
        return $this->jwtClaims[$key] ?? $default;
    }

    public function toArray(): array
    {
        return [
            'peer_spiffe_id' => $this->peerSpiffeId?->toString(),
            'peer_ip' => $this->peerIp,
            'mtls_authenticated' => $this->mtlsAuthenticated,
            'tls_cipher' => $this->tlsCipher,
            'jwt_claims' => $this->jwtClaims,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
