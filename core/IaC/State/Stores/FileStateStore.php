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

namespace NetFusion\Core\IaC\State\Stores;

use NetFusion\Core\IaC\State\Contracts\StateStoreInterface;
use NetFusion\Core\IaC\State\StateFile;
use NetFusion\Core\IaC\State\StateLock;

/**
 * Local filesystem state store supporting terraform.tfstate and .terraform.tfstate.lock.info.
 */
class FileStateStore implements StateStoreInterface
{
    private string $statePath;
    private string $lockPath;

    public function __construct(string $statePath = 'terraform.tfstate')
    {
        $this->statePath = $statePath;
        $this->lockPath = dirname($statePath) . '/.' . basename($statePath) . '.lock.info';
    }

    public function read(): ?StateFile
    {
        if (!file_exists($this->statePath)) {
            return null;
        }

        $json = file_get_contents($this->statePath);
        if ($json === false || trim($json) === '') {
            return null;
        }

        return StateFile::fromJson($json);
    }

    public function write(StateFile $state): void
    {
        $state->incrementSerial();
        $dir = dirname($this->statePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        file_put_contents($this->statePath, $state->toJson());
    }

    public function lock(StateLock $lock): bool
    {
        if (file_exists($this->lockPath)) {
            return false;
        }

        file_put_contents($this->lockPath, json_encode($lock->toArray(), JSON_PRETTY_PRINT));
        return true;
    }

    public function unlock(string $lockId): bool
    {
        if (!file_exists($this->lockPath)) {
            return true;
        }

        $data = json_decode((string) file_get_contents($this->lockPath), true);
        if (isset($data['ID']) && $data['ID'] === $lockId) {
            @unlink($this->lockPath);
            return true;
        }

        return false;
    }

    public function getActiveLock(): ?StateLock
    {
        if (!file_exists($this->lockPath)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($this->lockPath), true);
        return is_array($data) ? StateLock::fromArray($data) : null;
    }
}
