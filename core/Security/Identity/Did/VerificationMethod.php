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

namespace NetFusion\Core\Security\Identity\Did;

class VerificationMethod
{
    public function __construct(
        protected string $id,
        protected string $type,
        protected string $controller,
        protected ?string $publicKeyMultibase = null,
        protected ?array $publicKeyJwk = null,
        protected ?string $publicKeyHex = null
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getController(): string
    {
        return $this->controller;
    }

    public function getPublicKeyMultibase(): ?string
    {
        return $this->publicKeyMultibase;
    }

    public function getPublicKeyJwk(): ?array
    {
        return $this->publicKeyJwk;
    }

    public function getPublicKeyHex(): ?string
    {
        return $this->publicKeyHex;
    }

    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'type' => $this->type,
            'controller' => $this->controller,
        ];

        if ($this->publicKeyMultibase !== null) {
            $data['publicKeyMultibase'] = $this->publicKeyMultibase;
        }

        if ($this->publicKeyJwk !== null) {
            $data['publicKeyJwk'] = $this->publicKeyJwk;
        }

        if ($this->publicKeyHex !== null) {
            $data['publicKeyHex'] = $this->publicKeyHex;
        }

        return $data;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            type: (string) ($data['type'] ?? 'JsonWebKey2020'),
            controller: (string) ($data['controller'] ?? ''),
            publicKeyMultibase: isset($data['publicKeyMultibase']) ? (string) $data['publicKeyMultibase'] : null,
            publicKeyJwk: isset($data['publicKeyJwk']) && is_array($data['publicKeyJwk']) ? $data['publicKeyJwk'] : null,
            publicKeyHex: isset($data['publicKeyHex']) ? (string) $data['publicKeyHex'] : null
        );
    }
}
