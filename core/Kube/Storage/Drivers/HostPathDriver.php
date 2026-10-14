<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\Kube\Storage\Drivers;

use NetFusion\Core\Kube\Models\Pod;
use NetFusion\Core\Kube\Storage\VolumeInterface;

/**
 * HostPath volume plugin mounting a path from the host node filesystem into a Pod.
 */
class HostPathDriver implements VolumeInterface
{
    public function __construct(
        protected string $name,
        protected string $hostBasePath
    ) {
        $this->hostBasePath = rtrim($hostBasePath, '/\\');
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function mount(string $mountPath, Pod $pod): bool
    {
        if (!is_dir($this->hostBasePath)) {
            @mkdir($this->hostBasePath, 0755, true);
        }
        return true;
    }

    public function unmount(string $mountPath, Pod $pod): bool
    {
        return true;
    }

    public function write(string $path, string $content): bool
    {
        $target = $this->resolvePath($path);
        $dir = dirname($target);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return file_put_contents($target, $content) !== false;
    }

    public function read(string $path): ?string
    {
        $target = $this->resolvePath($path);
        if (file_exists($target)) {
            return (string) file_get_contents($target);
        }
        return null;
    }

    public function exists(string $path): bool
    {
        return file_exists($this->resolvePath($path));
    }

    protected function resolvePath(string $subPath): string
    {
        $clean = ltrim($subPath, '/\\');
        return $this->hostBasePath . DIRECTORY_SEPARATOR . $clean;
    }
}
