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
 * Distributed state lock metadata matching Terraform locking protocol.
 */
class StateLock implements JsonSerializable
{
    public function __construct(
        public readonly string $lockId,
        public readonly string $operation = 'Apply',
        public readonly string $who = 'netfusion@cli',
        public readonly string $version = '1.5.0',
        public readonly int $created = 0,
        public readonly string $path = 'default.tfstate'
    ) {
    }

    public function toArray(): array
    {
        return [
            'ID' => $this->lockId,
            'Operation' => $this->operation,
            'Info' => '',
            'Who' => $this->who,
            'Version' => $this->version,
            'Created' => date('c', $this->created ?: time()),
            'Path' => $this->path,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            lockId: (string) ($data['ID'] ?? ($data['lock_id'] ?? uniqid('lock_', true))),
            operation: (string) ($data['Operation'] ?? ($data['operation'] ?? 'Apply')),
            who: (string) ($data['Who'] ?? ($data['who'] ?? 'netfusion@cli')),
            version: (string) ($data['Version'] ?? ($data['version'] ?? '1.5.0')),
            created: isset($data['Created']) ? strtotime((string) $data['Created']) : time(),
            path: (string) ($data['Path'] ?? ($data['path'] ?? 'default.tfstate'))
        );
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
