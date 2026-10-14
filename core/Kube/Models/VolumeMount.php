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

namespace NetFusion\Core\Kube\Models;

/**
 * Describes a volume mount inside a container.
 */
class VolumeMount
{
    public function __construct(
        protected string $name,
        protected string $mountPath,
        protected bool $readOnly = false,
        protected ?string $subPath = null
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getMountPath(): string
    {
        return $this->mountPath;
    }

    public function isReadOnly(): bool
    {
        return $this->readOnly;
    }

    public function getSubPath(): ?string
    {
        return $this->subPath;
    }
}
