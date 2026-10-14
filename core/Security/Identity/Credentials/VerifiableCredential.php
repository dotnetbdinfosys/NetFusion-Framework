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

namespace NetFusion\Core\Security\Identity\Credentials;

use JsonSerializable;
use NetFusion\Core\Security\Identity\Exceptions\IdentityException;

class VerifiableCredential implements JsonSerializable
{
    public function __construct(
        protected string $id,
        protected string $issuer,
        protected array $credentialSubject,
        protected array $type = ['VerifiableCredential'],
        protected array $context = ['https://www.w3.org/2018/credentials/v1'],
        protected ?string $issuanceDate = null,
        protected ?string $expirationDate = null,
        protected ?array $credentialStatus = null,
        protected ?array $proof = null
    ) {
        $this->issuanceDate = $issuanceDate ?? gmdate('Y-m-d\TH:i:s\Z');
        if (!in_array('VerifiableCredential', $this->type, true)) {
            array_unshift($this->type, 'VerifiableCredential');
        }
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getIssuer(): string
    {
        return $this->issuer;
    }

    public function getCredentialSubject(): array
    {
        return $this->credentialSubject;
    }

    public function getType(): array
    {
        return $this->type;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function getIssuanceDate(): string
    {
        return $this->issuanceDate;
    }

    public function getExpirationDate(): ?string
    {
        return $this->expirationDate;
    }

    public function getCredentialStatus(): ?array
    {
        return $this->credentialStatus;
    }

    public function setCredentialStatus(?array $status): void
    {
        $this->credentialStatus = $status;
    }

    public function getProof(): ?array
    {
        return $this->proof;
    }

    public function setProof(array $proof): void
    {
        $this->proof = $proof;
    }

    public function isExpired(): bool
    {
        if ($this->expirationDate === null) {
            return false;
        }

        return strtotime($this->expirationDate) < time();
    }

    /**
     * Compute deterministic canonical hash for cryptographic signing
     */
    public function getCanonicalDigest(): string
    {
        $data = $this->toArray();
        unset($data['proof']);

        $normalized = $this->normalizeArray($data);
        $json = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return hash('sha256', $json);
    }

    /**
     * Recursively sort associative keys to guarantee deterministic serialization
     */
    protected function normalizeArray(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        $isAssoc = array_keys($data) !== range(0, count($data) - 1);
        if ($isAssoc) {
            ksort($data);
        }

        foreach ($data as $k => $v) {
            $data[$k] = $this->normalizeArray($v);
        }

        return $data;
    }

    public function toArray(): array
    {
        $arr = [
            '@context' => $this->context,
            'id' => $this->id,
            'type' => $this->type,
            'issuer' => $this->issuer,
            'issuanceDate' => $this->issuanceDate,
            'credentialSubject' => $this->credentialSubject,
        ];

        if ($this->expirationDate !== null) {
            $arr['expirationDate'] = $this->expirationDate;
        }

        if ($this->credentialStatus !== null) {
            $arr['credentialStatus'] = $this->credentialStatus;
        }

        if ($this->proof !== null) {
            $arr['proof'] = $this->proof;
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
        return new self(
            id: (string) ($data['id'] ?? ''),
            issuer: (string) ($data['issuer'] ?? ''),
            credentialSubject: (array) ($data['credentialSubject'] ?? []),
            type: (array) ($data['type'] ?? ['VerifiableCredential']),
            context: (array) ($data['@context'] ?? ['https://www.w3.org/2018/credentials/v1']),
            issuanceDate: isset($data['issuanceDate']) ? (string) $data['issuanceDate'] : null,
            expirationDate: isset($data['expirationDate']) ? (string) $data['expirationDate'] : null,
            credentialStatus: isset($data['credentialStatus']) ? (array) $data['credentialStatus'] : null,
            proof: isset($data['proof']) ? (array) $data['proof'] : null
        );
    }

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new IdentityException("Malformed JSON for Verifiable Credential.");
        }
        return self::fromArray($data);
    }
}
