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
use NetFusion\Core\Kube\Storage\Drivers\MemoryTmpfsDriver;

/**
 * Orchestrates volume attachment and lifecycle mounting across Pod containers.
 */
class VolumeAttachmentManager
{
    /**
     * @var array<string, VolumeInterface> Registered storage volume plugins keyed by volume name
     */
    protected array $volumes = [];

    public function registerVolume(VolumeInterface $volume): self
    {
        $this->volumes[$volume->getName()] = $volume;
        return $this;
    }

    public function getVolume(string $name): ?VolumeInterface
    {
        return $this->volumes[$name] ?? null;
    }

    /**
     * Mount all volumes declared in Pod container specs.
     */
    public function mountPodVolumes(Pod $pod): void
    {
        foreach ($pod->getContainers() as $container) {
            foreach ($container->getVolumeMounts() as $mount) {
                $volName = $mount->getName();
                $volume = $this->volumes[$volName] ?? null;

                // Auto-provision MemoryTmpfsDriver if not registered
                if ($volume === null) {
                    $volume = new MemoryTmpfsDriver($volName);
                    $this->volumes[$volName] = $volume;
                }

                $volume->mount($mount->getMountPath(), $pod);
            }
        }
    }

    /**
     * Unmount all volumes attached to a terminating Pod.
     */
    public function unmountPodVolumes(Pod $pod): void
    {
        foreach ($pod->getContainers() as $container) {
            foreach ($container->getVolumeMounts() as $mount) {
                $volName = $mount->getName();
                if (isset($this->volumes[$volName])) {
                    $this->volumes[$volName]->unmount($mount->getMountPath(), $pod);
                }
            }
        }
    }
}
