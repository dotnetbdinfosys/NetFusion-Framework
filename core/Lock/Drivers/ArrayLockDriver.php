<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Lock/Drivers/ArrayLockDriver.php
| Module    : Lock
| Namespace : NetFusion\Core\Lock\Drivers
| Purpose   : In-memory atomic lock driver for high-speed testing and simulation.
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

namespace NetFusion\Core\Lock\Drivers;

use NetFusion\Core\Lock\Contracts\LockDriverInterface;

class ArrayLockDriver implements LockDriverInterface
{
    /**
     * @var array<string, array{owner: string, expiration: int, created_at: string}>
     */
    protected array $locks = [];

    public function acquire(string $name, string $owner, int $seconds): bool
    {
        $now = time();

        if (isset($this->locks[$name])) {
            $current = $this->locks[$name];
            if ($current['expiration'] > $now && $current['owner'] !== $owner) {
                return false;
            }
        }

        $expiration = $seconds !== 0 ? $now + $seconds : $now + 86400 * 365;
        $this->locks[$name] = [
            'owner'      => $owner,
            'expiration' => $expiration,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        return true;
    }

    public function release(string $name, string $owner): bool
    {
        if (isset($this->locks[$name]) && $this->locks[$name]['owner'] === $owner) {
            unset($this->locks[$name]);
            return true;
        }

        return false;
    }

    public function forceRelease(string $name): void
    {
        unset($this->locks[$name]);
    }

    public function getCurrentOwner(string $name): ?string
    {
        if (!isset($this->locks[$name])) {
            return null;
        }

        if ($this->locks[$name]['expiration'] <= time()) {
            unset($this->locks[$name]);
            return null;
        }

        return $this->locks[$name]['owner'];
    }

    public function isLocked(string $name): bool
    {
        return $this->getCurrentOwner($name) !== null;
    }

    public function all(): array
    {
        $result = [];
        foreach ($this->locks as $key => $data) {
            $result[] = [
                'key'        => $key,
                'owner'      => $data['owner'],
                'expiration' => $data['expiration'],
                'created_at' => $data['created_at'],
            ];
        }
        return $result;
    }

    public function cleanExpired(): int
    {
        $count = 0;
        $now = time();
        foreach ($this->locks as $key => $data) {
            if ($data['expiration'] <= $now) {
                unset($this->locks[$key]);
                $count++;
            }
        }
        return $count;
    }
}