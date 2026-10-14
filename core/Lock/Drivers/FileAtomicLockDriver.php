<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Lock/Drivers/FileAtomicLockDriver.php
| Module    : Lock
| Namespace : NetFusion\Core\Lock\Drivers
| Purpose   : File-system based atomic lock driver with TTL and flock.
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

class FileAtomicLockDriver implements LockDriverInterface
{
    public function __construct(protected string $storagePath)
    {
        if (!is_dir($this->storagePath)) {
            @mkdir($this->storagePath, 0755, true);
        }
    }

    protected function getFilePath(string $name): string
    {
        $hash = md5($name);
        return $this->storagePath . DIRECTORY_SEPARATOR . 'lock_' . $hash . '.lock';
    }

    public function acquire(string $name, string $owner, int $seconds): bool
    {
        $file = $this->getFilePath($name);
        $fp = @fopen($file, 'c+');
        if (!$fp) {
            return false;
        }

        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            fclose($fp);
            return false;
        }

        $now = time();
        $content = stream_get_contents($fp);
        $data = !empty($content) ? json_decode($content, true) : null;

        if ($data && isset($data['expiration'], $data['owner'])) {
            if ((int)$data['expiration'] > $now && (string)$data['owner'] !== $owner) {
                flock($fp, LOCK_UN);
                fclose($fp);
                return false;
            }
        }

        $expiration = $seconds !== 0 ? $now + $seconds : $now + 86400 * 365;
        $newData = [
            'name'       => $name,
            'owner'      => $owner,
            'expiration' => $expiration,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($newData, JSON_PRETTY_PRINT));
        fflush($fp);

        flock($fp, LOCK_UN);
        fclose($fp);
        return true;
    }

    public function release(string $name, string $owner): bool
    {
        $file = $this->getFilePath($name);
        if (!file_exists($file)) {
            return true;
        }

        $fp = @fopen($file, 'c+');
        if (!$fp) {
            return false;
        }

        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            return false;
        }

        $content = stream_get_contents($fp);
        $data = !empty($content) ? json_decode($content, true) : null;

        if ($data && (string)$data['owner'] === $owner) {
            ftruncate($fp, 0);
            flock($fp, LOCK_UN);
            fclose($fp);
            @unlink($file);
            return true;
        }

        flock($fp, LOCK_UN);
        fclose($fp);
        return false;
    }

    public function forceRelease(string $name): void
    {
        $file = $this->getFilePath($name);
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    public function getCurrentOwner(string $name): ?string
    {
        $file = $this->getFilePath($name);
        if (!file_exists($file)) {
            return null;
        }

        $content = @file_get_contents($file);
        $data = !empty($content) ? json_decode($content, true) : null;

        if (!$data || !isset($data['expiration'], $data['owner'])) {
            return null;
        }

        if ((int)$data['expiration'] <= time()) {
            return null;
        }

        return (string)$data['owner'];
    }

    public function isLocked(string $name): bool
    {
        return $this->getCurrentOwner($name) !== null;
    }

    public function all(): array
    {
        $locks = [];
        $files = glob($this->storagePath . DIRECTORY_SEPARATOR . 'lock_*.lock') ?: [];

        foreach ($files as $file) {
            $content = @file_get_contents($file);
            $data = !empty($content) ? json_decode($content, true) : null;
            if ($data && isset($data['name'], $data['expiration'])) {
                $locks[] = [
                    'key'        => $data['name'],
                    'owner'      => $data['owner'] ?? '',
                    'expiration' => (int)$data['expiration'],
                    'created_at' => $data['created_at'] ?? '',
                ];
            }
        }

        return $locks;
    }

    public function cleanExpired(): int
    {
        $count = 0;
        $now = time();
        $files = glob($this->storagePath . DIRECTORY_SEPARATOR . 'lock_*.lock') ?: [];

        foreach ($files as $file) {
            $content = @file_get_contents($file);
            $data = !empty($content) ? json_decode($content, true) : null;
            if ($data && isset($data['expiration']) && (int)$data['expiration'] <= $now) {
                @unlink($file);
                $count++;
            }
        }

        return $count;
    }
}