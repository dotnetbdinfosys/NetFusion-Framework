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

namespace NetFusion\Core\Kube\Storage;

use NetFusion\Core\Kube\Models\Pod;

/**
 * Contract for Kubernetes Container Storage Interface (CSI) volume plugins.
 */
interface VolumeInterface
{
    public function getName(): string;

    /**
     * Mount volume into pod filesystem path.
     */
    public function mount(string $mountPath, Pod $pod): bool;

    /**
     * Unmount volume from pod filesystem path.
     */
    public function unmount(string $mountPath, Pod $pod): bool;

    /**
     * Write content to a file inside the mounted volume.
     */
    public function write(string $path, string $content): bool;

    /**
     * Read content of a file inside the mounted volume.
     */
    public function read(string $path): ?string;

    /**
     * Check if a file exists inside the volume.
     */
    public function exists(string $path): bool;
}
