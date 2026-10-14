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

class VerifiablePresentation implements JsonSerializable
{
    /**
     * @param array<VerifiableCredential|array> $verifiableCredential
     */
    public function __construct(
        protected string $id,
        protected string $holder,
        protected array $verifiableCredential = [],
        protected array $type = ['VerifiablePresentation'],
        protected array $context = ['https://www.w3.org/2018/credentials/v1'],
        protected ?array $proof = null
    ) {
        if (!in_array('VerifiablePresentation', $this->type, true)) {
            array_unshift($this->type, 'VerifiablePresentation');
        }
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getHolder(): string
    {
        return $this->holder;
    }

    public function getVerifiableCredentials(): array
    {
        return $this->verifiableCredential;
    }

    public function addCredential(VerifiableCredential|array $credential): void
    {
        $this->verifiableCredential[] = $credential;
    }

    public function getType(): array
    {
        return $this->type;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function getProof(): ?array
    {
        return $this->proof;
    }

    public function setProof(array $proof): void
    {
        $this->proof = $proof;
    }

    public function getCanonicalDigest(): string
    {
        $data = $this->toArray();
        unset($data['proof']);

        $normalized = $this->normalizeArray($data);
        $json = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return hash('sha256', $json);
    }

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
        $credentials = [];
        foreach ($this->verifiableCredential as $vc) {
            $credentials[] = $vc instanceof VerifiableCredential ? $vc->toArray() : $vc;
        }

        $arr = [
            '@context' => $this->context,
            'id' => $this->id,
            'type' => $this->type,
            'holder' => $this->holder,
            'verifiableCredential' => $credentials,
        ];

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
        $vcs = [];
        foreach ($data['verifiableCredential'] ?? [] as $vcData) {
            if (is_array($vcData)) {
                $vcs[] = isset($vcData['credentialSubject']) ? VerifiableCredential::fromArray($vcData) : $vcData;
            }
        }

        return new self(
            id: (string) ($data['id'] ?? ''),
            holder: (string) ($data['holder'] ?? ''),
            verifiableCredential: $vcs,
            type: (array) ($data['type'] ?? ['VerifiablePresentation']),
            context: (array) ($data['@context'] ?? ['https://www.w3.org/2018/credentials/v1']),
            proof: isset($data['proof']) ? (array) $data['proof'] : null
        );
    }

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new IdentityException("Malformed JSON for Verifiable Presentation.");
        }
        return self::fromArray($data);
    }
}
