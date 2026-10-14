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
 * Pod Toleration definition.
 */
class Toleration
{
    /**
     * @param string $key Taint key to match
     * @param string $operator 'Equal' or 'Exists'
     * @param string|null $value Taint value (ignored if operator is Exists)
     * @param TaintEffect|null $effect Matching taint effect, or null for all effects
     * @param int|null $tolerationSeconds
     */
    public function __construct(
        protected string $key,
        protected string $operator = 'Equal',
        protected ?string $value = null,
        protected ?TaintEffect $effect = null,
        protected ?int $tolerationSeconds = null
    ) {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getOperator(): string
    {
        return $this->operator;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function getEffect(): ?TaintEffect
    {
        return $this->effect;
    }

    public function getTolerationSeconds(): ?int
    {
        return $this->tolerationSeconds;
    }

    /**
     * Check if this toleration tolerates the given node taint.
     */
    public function tolerates(Taint $taint): bool
    {
        if ($this->key !== '' && $this->key !== $taint->getKey()) {
            return false;
        }

        if ($this->effect !== null && $this->effect !== $taint->getEffect()) {
            return false;
        }

        if ($this->operator === 'Exists') {
            return true;
        }

        return $this->value === $taint->getValue();
    }
}
