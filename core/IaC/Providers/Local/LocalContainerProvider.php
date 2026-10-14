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

namespace NetFusion\Core\IaC\Providers\Local;

use NetFusion\Core\IaC\Providers\Contracts\ResourceProviderInterface;

/**
 * Local container and filesystem provider for self-hosted orchestration.
 */
class LocalContainerProvider implements ResourceProviderInterface
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $liveResources = [];

    public function getName(): string
    {
        return 'local';
    }

    public function supports(string $resourceType): bool
    {
        return str_starts_with($resourceType, 'local_');
    }

    public function create(string $type, string $name, array $attributes): array
    {
        $id = $type . '-' . $name;
        $computed = array_merge($attributes, [
            'id' => $id,
            'status' => 'running',
            'pid' => rand(1000, 99999),
            'created_at' => time(),
        ]);

        $this->liveResources[$id] = $computed;
        return $computed;
    }

    public function read(string $type, string $id): ?array
    {
        return $this->liveResources[$id] ?? null;
    }

    public function update(string $type, string $id, array $attributes): array
    {
        $existing = $this->liveResources[$id] ?? ['id' => $id];
        $updated = array_merge($existing, $attributes, ['updated_at' => time()]);
        $this->liveResources[$id] = $updated;
        return $updated;
    }

    public function delete(string $type, string $id): bool
    {
        unset($this->liveResources[$id]);
        return true;
    }
}
