<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Features/Contracts/FeatureDriverInterface.php
| Module    : Features\Contracts
| Namespace : NetFusion\Core\Features\Contracts
| Purpose   : Storage driver contract for Feature Flags and A/B experiments.
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

namespace NetFusion\Core\Features\Contracts;

/**
 * Interface FeatureDriverInterface
 *
 * Defines persistence contract for feature flag configurations.
 *
 * @package NetFusion\Core\Features\Contracts
 */
interface FeatureDriverInterface
{
    /**
     * Retrieve all configured feature flags.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getAll(): array;

    /**
     * Retrieve a specific feature flag configuration.
     *
     * @param string $feature
     * @return array<string, mixed>|null
     */
    public function get(string $feature): ?array;

    /**
     * Store or update a feature flag configuration.
     *
     * @param string               $feature
     * @param array<string, mixed> $data
     * @return bool
     */
    public function set(string $feature, array $data): bool;

    /**
     * Delete a feature flag configuration.
     *
     * @param string $feature
     * @return bool
     */
    public function delete(string $feature): bool;

    /**
     * Flush all feature flag records.
     *
     * @return bool
     */
    public function purge(): bool;
}
