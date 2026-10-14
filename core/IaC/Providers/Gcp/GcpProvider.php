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

namespace NetFusion\Core\IaC\Providers\Gcp;

use NetFusion\Core\IaC\Providers\Contracts\ResourceProviderInterface;

/**
 * Google Cloud Platform (GCP) infrastructure provider driver.
 */
class GcpProvider implements ResourceProviderInterface
{
    private string $project;
    private string $zone;

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $liveResources = [];

    public function __construct(string $project = 'netfusion-core', string $zone = 'us-central1-a')
    {
        $this->project = $project;
        $this->zone = $zone;
    }

    public function getName(): string
    {
        return 'google';
    }

    public function supports(string $resourceType): bool
    {
        return str_starts_with($resourceType, 'google_');
    }

    public function create(string $type, string $name, array $attributes): array
    {
        $id = 'projects/' . $this->project . '/' . $type . '/' . $name;
        $selfLink = "https://www.googleapis.com/compute/v1/{$id}";

        $computed = array_merge($attributes, [
            'id' => $id,
            'self_link' => $selfLink,
            'project' => $this->project,
            'zone' => $this->zone,
            'status' => 'RUNNING',
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
