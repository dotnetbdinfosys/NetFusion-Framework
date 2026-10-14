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

namespace NetFusion\Core\SDUI\Rules;

/**
 * Contextual rule engine for evaluating component visibility and variant personalization.
 */
class PersonalizationRule
{
    /** @var array<string, mixed> */
    protected array $criteria = [];

    /** @var callable|null */
    protected mixed $customCallback = null;

    public function __construct(array $criteria = [], ?callable $customCallback = null)
    {
        $this->criteria = $criteria;
        $this->customCallback = $customCallback;
    }

    public static function create(): self
    {
        return new self();
    }

    public function requireRole(string ...$roles): self
    {
        $this->criteria['roles'] = $roles;
        return $this;
    }

    public function requirePlatform(string ...$platforms): self
    {
        $this->criteria['platforms'] = $platforms;
        return $this;
    }

    public function requireAttribute(string $key, mixed $value): self
    {
        $this->criteria['attributes'][$key] = $value;
        return $this;
    }

    public function when(callable $callback): self
    {
        $this->customCallback = $callback;
        return $this;
    }

    /**
     * Evaluate rule against given execution context.
     *
     * @param array<string, mixed> $context
     */
    public function matches(array $context): bool
    {
        // Check roles
        if (!empty($this->criteria['roles'])) {
            $userRoles = (array) ($context['user']['roles'] ?? $context['roles'] ?? []);
            $matched = false;
            foreach ($this->criteria['roles'] as $requiredRole) {
                if (in_array($requiredRole, $userRoles, true)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                return false;
            }
        }

        // Check platforms
        if (!empty($this->criteria['platforms'])) {
            $currentPlatform = $context['platform'] ?? 'all';
            if (!in_array($currentPlatform, $this->criteria['platforms'], true) && !in_array('all', $this->criteria['platforms'], true)) {
                return false;
            }
        }

        // Check attributes
        if (!empty($this->criteria['attributes'])) {
            foreach ($this->criteria['attributes'] as $attrKey => $expectedVal) {
                $actualVal = $context['user'][$attrKey] ?? $context[$attrKey] ?? null;
                if ($actualVal !== $expectedVal) {
                    return false;
                }
            }
        }

        // Custom callback
        if ($this->customCallback !== null) {
            return (bool) ($this->customCallback)($context);
        }

        return true;
    }
}
