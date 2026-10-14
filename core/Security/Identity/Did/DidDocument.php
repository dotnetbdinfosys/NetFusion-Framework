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

use JsonSerializable;
use NetFusion\Core\Security\Identity\Exceptions\InvalidDidDocumentException;

class DidDocument implements JsonSerializable
{
    /**
     * @param VerificationMethod[] $verificationMethod
     */
    public function __construct(
        protected string $id,
        protected array $context = ['https://www.w3.org/ns/did/v1'],
        protected array $verificationMethod = [],
        protected array $authentication = [],
        protected array $assertionMethod = [],
        protected array $keyAgreement = [],
        protected array $service = [],
        protected bool $deactivated = false
    ) {
        if (empty($this->id)) {
            throw new InvalidDidDocumentException("DID Document 'id' cannot be empty.");
        }
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * @return VerificationMethod[]
     */
    public function getVerificationMethods(): array
    {
        return $this->verificationMethod;
    }

    public function getAuthentication(): array
    {
        return $this->authentication;
    }

    public function getAssertionMethod(): array
    {
        return $this->assertionMethod;
    }

    public function getKeyAgreement(): array
    {
        return $this->keyAgreement;
    }

    public function getServices(): array
    {
        return $this->service;
    }

    public function isDeactivated(): bool
    {
        return $this->deactivated;
    }

    public function deactivate(): void
    {
        $this->deactivated = true;
    }

    public function addVerificationMethod(VerificationMethod $method, bool $asAssertion = true, bool $asAuthentication = true): void
    {
        $this->verificationMethod[$method->getId()] = $method;

        if ($asAssertion && !in_array($method->getId(), $this->assertionMethod, true)) {
            $this->assertionMethod[] = $method->getId();
        }

        if ($asAuthentication && !in_array($method->getId(), $this->authentication, true)) {
            $this->authentication[] = $method->getId();
        }
    }

    public function getVerificationMethod(string $id): ?VerificationMethod
    {
        return $this->verificationMethod[$id] ?? null;
    }

    public function findVerificationMethodForAssertion(): ?VerificationMethod
    {
        if (!empty($this->assertionMethod)) {
            $assertionId = reset($this->assertionMethod);
            return $this->getVerificationMethod($assertionId);
        }

        if (!empty($this->verificationMethod)) {
            return reset($this->verificationMethod);
        }

        return null;
    }

    public function toArray(): array
    {
        $vms = [];
        foreach ($this->verificationMethod as $vm) {
            $vms[] = $vm->toArray();
        }

        $arr = [
            '@context' => $this->context,
            'id' => $this->id,
            'verificationMethod' => $vms,
            'authentication' => array_values($this->authentication),
            'assertionMethod' => array_values($this->assertionMethod),
        ];

        if (!empty($this->keyAgreement)) {
            $arr['keyAgreement'] = array_values($this->keyAgreement);
        }

        if (!empty($this->service)) {
            $arr['service'] = array_values($this->service);
        }

        if ($this->deactivated) {
            $arr['deactivated'] = true;
        }

        return $arr;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    public static function fromArray(array $data): self
    {
        $id = (string) ($data['id'] ?? '');
        $context = (array) ($data['@context'] ?? ['https://www.w3.org/ns/did/v1']);
        $vms = [];

        foreach ($data['verificationMethod'] ?? [] as $vmData) {
            if (is_array($vmData)) {
                $vm = VerificationMethod::fromArray($vmData);
                $vms[$vm->getId()] = $vm;
            }
        }

        return new self(
            id: $id,
            context: $context,
            verificationMethod: $vms,
            authentication: (array) ($data['authentication'] ?? []),
            assertionMethod: (array) ($data['assertionMethod'] ?? []),
            keyAgreement: (array) ($data['keyAgreement'] ?? []),
            service: (array) ($data['service'] ?? []),
            deactivated: (bool) ($data['deactivated'] ?? false)
        );
    }

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new InvalidDidDocumentException("Invalid JSON format for DID Document.");
        }
        return self::fromArray($data);
    }
}
