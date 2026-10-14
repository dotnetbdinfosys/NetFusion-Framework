<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Lock/Lock.php
| Module    : Lock
| Namespace : NetFusion\Core\Lock
| Purpose   : Core Lock implementation with spinlock backoff and closure execution.
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

namespace NetFusion\Core\Lock;

use NetFusion\Core\Lock\Contracts\LockDriverInterface;
use NetFusion\Core\Lock\Contracts\LockInterface;
use NetFusion\Core\Lock\Exceptions\LockTimeoutException;

class Lock implements LockInterface
{
    protected string $owner;

    public function __construct(
        protected LockDriverInterface $driver,
        protected string $name,
        protected int $seconds = 60,
        ?string $owner = null
    ) {
        $this->owner = $owner ?? bin2hex(random_bytes(16));
    }

    public function acquire(): bool
    {
        return $this->driver->acquire($this->name, $this->owner, $this->seconds);
    }

    public function release(): bool
    {
        return $this->driver->release($this->name, $this->owner);
    }

    public function forceRelease(): void
    {
        $this->driver->forceRelease($this->name);
    }

    public function getOwner(): string
    {
        return $this->owner;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isAcquired(): bool
    {
        return $this->driver->isLocked($this->name);
    }

    public function isAcquiredByCurrentProcess(): bool
    {
        return $this->driver->getCurrentOwner($this->name) === $this->owner;
    }

    public function get(?callable $callback = null): mixed
    {
        if ($this->acquire()) {
            if ($callback === null) {
                return true;
            }

            try {
                return $callback();
            } finally {
                $this->release();
            }
        }

        return false;
    }

    public function block(int $waitTimeoutSeconds, ?callable $callback = null): mixed
    {
        $starting = microtime(true);
        $maxTime = $starting + $waitTimeoutSeconds;

        while (true) {
            if ($this->acquire()) {
                if ($callback === null) {
                    return true;
                }

                try {
                    return $callback();
                } finally {
                    $this->release();
                }
            }

            if (microtime(true) >= $maxTime) {
                throw LockTimeoutException::timedOut($this->name, $waitTimeoutSeconds);
            }

            // Sleep 50ms with jitter to prevent thundering herd
            usleep(random_int(25000, 75000));
        }
    }
}