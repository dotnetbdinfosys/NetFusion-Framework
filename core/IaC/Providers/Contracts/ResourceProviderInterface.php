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

namespace NetFusion\Core\IaC\Providers\Contracts;

/**
 * Contract for cloud infrastructure resource drivers (AWS, GCP, Azure, Local).
 */
interface ResourceProviderInterface
{
    public function getName(): string;

    /**
     * Check whether this provider manages the specified resource type.
     */
    public function supports(string $resourceType): bool;

    /**
     * Provision a new cloud resource and return its computed attributes.
     *
     * @param string $type
     * @param string $name
     * @param array<string, mixed> $attributes
     * @return array<string, mixed> Complete resource attributes including server IDs and ARNs
     */
    public function create(string $type, string $name, array $attributes): array;

    /**
     * Read instantaneous live state of an existing cloud resource.
     */
    public function read(string $type, string $id): ?array;

    /**
     * Update an existing cloud resource in-place.
     */
    public function update(string $type, string $id, array $attributes): array;

    /**
     * Terminate / destroy an existing cloud resource.
     */
    public function delete(string $type, string $id): bool;
}
