<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Features/Drivers/CacheDriver.php
| Module    : Features\Drivers
| Namespace : NetFusion\Core\Features\Drivers
| Purpose   : Cache storage driver for Feature Flags.
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

use NetFusion\Core\Cache\Contracts\CacheInterface;
use NetFusion\Core\Features\Contracts\FeatureDriverInterface;

/**
 * Class CacheDriver
 *
 * Cache-backed distributed feature flag storage.
 *
 * @package NetFusion\Core\Features\Drivers
 */
class CacheDriver implements FeatureDriverInterface
{
    protected const INDEX_KEY = 'netfusion:features:index';
    protected const PREFIX = 'netfusion:feature:';

    public function __construct(
        protected CacheInterface $cache,
        protected int $ttl = 86400
    ) {
    }

    public function getAll(): array
    {
        $index = $this->getIndex();
        $features = [];

        foreach ($index as $name) {
            $data = $this->get($name);
            if ($data !== null) {
                $features[$name] = $data;
            }
        }

        return $features;
    }

    public function get(string $feature): ?array
    {
        $val = $this->cache->get(self::PREFIX . $feature);
        return is_array($val) ? $val : null;
    }

    public function set(string $feature, array $data): bool
    {
        $this->cache->set(self::PREFIX . $feature, $data, $this->ttl);
        $this->addToIndex($feature);
        return true;
    }

    public function delete(string $feature): bool
    {
        $this->cache->delete(self::PREFIX . $feature);
        $this->removeFromIndex($feature);
        return true;
    }

    public function purge(): bool
    {
        $index = $this->getIndex();
        foreach ($index as $name) {
            $this->cache->delete(self::PREFIX . $name);
        }
        $this->cache->delete(self::INDEX_KEY);
        return true;
    }

    protected function getIndex(): array
    {
        $idx = $this->cache->get(self::INDEX_KEY);
        return is_array($idx) ? $idx : [];
    }

    protected function addToIndex(string $feature): void
    {
        $index = $this->getIndex();
        if (! in_array($feature, $index, true)) {
            $index[] = $feature;
            $this->cache->set(self::INDEX_KEY, $index, $this->ttl);
        }
    }

    protected function removeFromIndex(string $feature): void
    {
        $index = $this->getIndex();
        $filtered = array_values(array_filter($index, fn($f) => $f !== $feature));
        $this->cache->set(self::INDEX_KEY, $filtered, $this->ttl);
    }
}
