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

namespace NetFusion\Core\IaC\State;

use JsonSerializable;

/**
 * Official Terraform v4 state format serializer and deserializer.
 */
class StateFile implements JsonSerializable
{
    public int $version = 4;
    public string $terraformVersion = '1.5.0';
    public int $serial = 1;
    public string $lineage;

    /**
     * @var array<string, array{value: mixed, type: string, sensitive: bool}>
     */
    public array $outputs = [];

    /**
     * @var array<string, StateResource> Resources keyed by canonical address (e.g. "aws_instance.web")
     */
    public array $resources = [];

    public function __construct(
        int $version = 4,
        string $terraformVersion = '1.5.0',
        int $serial = 1,
        ?string $lineage = null,
        array $outputs = [],
        array $resources = []
    ) {
        $this->version = $version;
        $this->terraformVersion = $terraformVersion;
        $this->serial = $serial;
        $this->lineage = $lineage ?? self::generateUuid();
        $this->outputs = $outputs;

        foreach ($resources as $res) {
            if ($res instanceof StateResource) {
                $this->resources[$res->getAddress()] = $res;
            }
        }
    }

    public function getResource(string $address): ?StateResource
    {
        return $this->resources[$address] ?? null;
    }

    public function hasResource(string $address): bool
    {
        return isset($this->resources[$address]);
    }

    public function setResource(StateResource $resource): void
    {
        $this->resources[$resource->getAddress()] = $resource;
    }

    public function removeResource(string $address): void
    {
        unset($this->resources[$address]);
    }

    public function incrementSerial(): void
    {
        $this->serial++;
    }

    public function setOutput(string $key, mixed $value, string $type = 'string', bool $sensitive = false): void
    {
        $this->outputs[$key] = [
            'value' => $value,
            'type' => $type,
            'sensitive' => $sensitive,
        ];
    }

    public function getOutput(string $key): mixed
    {
        return $this->outputs[$key]['value'] ?? null;
    }

    /**
     * Compute SHA-256 cryptographic checksum of canonical JSON state.
     */
    public function computeChecksum(): string
    {
        return hash('sha256', $this->toJson());
    }

    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'terraform_version' => $this->terraformVersion,
            'serial' => $this->serial,
            'lineage' => $this->lineage,
            'outputs' => $this->outputs,
            'resources' => array_values(array_map(fn(StateResource $r) => $r->toArray(), $this->resources)),
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public static function fromArray(array $data): self
    {
        $resources = [];
        if (!empty($data['resources']) && is_array($data['resources'])) {
            foreach ($data['resources'] as $resData) {
                $res = $resData instanceof StateResource ? $resData : StateResource::fromArray($resData);
                $resources[] = $res;
            }
        }

        return new self(
            version: (int) ($data['version'] ?? 4),
            terraformVersion: (string) ($data['terraform_version'] ?? '1.5.0'),
            serial: (int) ($data['serial'] ?? 1),
            lineage: (string) ($data['lineage'] ?? self::generateUuid()),
            outputs: (array) ($data['outputs'] ?? []),
            resources: $resources
        );
    }

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new \InvalidArgumentException("Invalid JSON provided for StateFile deserialization.");
        }
        return self::fromArray($data);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private static function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); // version 4
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // variant 1
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
