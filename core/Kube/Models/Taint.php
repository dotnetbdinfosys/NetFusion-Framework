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

use NetFusion\Core\Kube\Enums\TaintEffect;

/**
 * Node Taint definition.
 */
class Taint
{
    public function __construct(
        protected string $key,
        protected ?string $value = null,
        protected TaintEffect $effect = TaintEffect::NO_SCHEDULE
    ) {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function getEffect(): TaintEffect
    {
        return $this->effect;
    }
}
