<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Features/Drivers/ArrayDriver.php
| Module    : Features\Drivers
| Namespace : NetFusion\Core\Features\Drivers
| Purpose   : In-memory transient storage driver for Feature Flags.
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

namespace NetFusion\Core\Features\Drivers;

use NetFusion\Core\Features\Contracts\FeatureDriverInterface;

/**
 * Class ArrayDriver
 *
 * Ephemeral memory-backed feature flag storage.
 *
 * @package NetFusion\Core\Features\Drivers
 */
class ArrayDriver implements FeatureDriverInterface
{
    /**
     * @param array<string, array<string, mixed>> $features
     */
    public function __construct(protected array $features = [])
    {
    }

    public function getAll(): array
    {
        return $this->features;
    }

    public function get(string $feature): ?array
    {
        return $this->features[$feature] ?? null;
    }

    public function set(string $feature, array $data): bool
    {
        $this->features[$feature] = $data;
        return true;
    }

    public function delete(string $feature): bool
    {
        unset($this->features[$feature]);
        return true;
    }

    public function purge(): bool
    {
        $this->features = [];
        return true;
    }
}
