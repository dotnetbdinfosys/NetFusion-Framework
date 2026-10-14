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
 * Concrete instance of a managed infrastructure resource in Terraform State v4.
 */
class StateResourceInstance implements JsonSerializable
{
    /**
     * @param int $schemaVersion
     * @param array<string, mixed> $attributes
     * @param array<string> $dependencies
     * @param string|null $privateState
     */
    public function __construct(
        public readonly int $schemaVersion = 0,
        public readonly array $attributes = [],
        public readonly array $dependencies = [],
        public readonly ?string $privateState = null
    ) {
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function toArray(): array
    {
        return [
            'schema_version' => $this->schemaVersion,
            'attributes' => $this->attributes,
            'dependencies' => $this->dependencies,
            'private' => $this->privateState,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            schemaVersion: (int) ($data['schema_version'] ?? 0),
            attributes: (array) ($data['attributes'] ?? []),
            dependencies: (array) ($data['dependencies'] ?? []),
            privateState: $data['private'] ?? null
        );
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
