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
 * Managed resource grouping matching Terraform State v4 schema.
 */
class StateResource implements JsonSerializable
{
    /**
     * @param string $mode 'managed' or 'data'
     * @param string $type Resource type (e.g. 'aws_instance')
     * @param string $name Resource name (e.g. 'web')
     * @param string $provider Provider reference
     * @param array<StateResourceInstance> $instances
     */
    public function __construct(
        public readonly string $mode,
        public readonly string $type,
        public readonly string $name,
        public readonly string $provider = 'provider["registry.terraform.io/hashicorp/aws"]',
        public readonly array $instances = []
    ) {
    }

    public function getAddress(): string
    {
        return ($this->mode === 'data') ? "data.{$this->type}.{$this->name}" : "{$this->type}.{$this->name}";
    }

    public function getPrimaryInstance(): ?StateResourceInstance
    {
        return $this->instances[0] ?? null;
    }

    public function toArray(): array
    {
        return [
            'mode' => $this->mode,
            'type' => $this->type,
            'name' => $this->name,
            'provider' => $this->provider,
            'instances' => array_map(fn(StateResourceInstance $i) => $i->toArray(), $this->instances),
        ];
    }

    public static function fromArray(array $data): self
    {
        $instances = [];
        if (!empty($data['instances']) && is_array($data['instances'])) {
            foreach ($data['instances'] as $inst) {
                $instances[] = $inst instanceof StateResourceInstance ? $inst : StateResourceInstance::fromArray($inst);
            }
        }

        return new self(
            mode: (string) ($data['mode'] ?? 'managed'),
            type: (string) ($data['type'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            provider: (string) ($data['provider'] ?? ''),
            instances: $instances
        );
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
