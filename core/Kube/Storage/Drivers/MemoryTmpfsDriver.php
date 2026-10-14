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
 * In-memory ephemeral tmpfs volume driver for emptyDir / scratchpad storage.
 */
class MemoryTmpfsDriver implements VolumeInterface
{
    /**
     * @var array<string, string> In-memory file storage: path => content
     */
    protected array $files = [];

    /**
     * @var array<string, bool> Active mount records: "podUid:mountPath" => true
     */
    protected array $activeMounts = [];

    public function __construct(
        protected string $name
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function mount(string $mountPath, Pod $pod): bool
    {
        $key = "{$pod->getUid()}:{$mountPath}";
        $this->activeMounts[$key] = true;
        return true;
    }

    public function unmount(string $mountPath, Pod $pod): bool
    {
        $key = "{$pod->getUid()}:{$mountPath}";
        unset($this->activeMounts[$key]);
        return true;
    }

    public function write(string $path, string $content): bool
    {
        $this->files[$path] = $content;
        return true;
    }

    public function read(string $path): ?string
    {
        return $this->files[$path] ?? null;
    }

    public function exists(string $path): bool
    {
        return isset($this->files[$path]);
    }

    public function clear(): void
    {
        $this->files = [];
        $this->activeMounts = [];
    }
}
