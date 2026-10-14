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

namespace NetFusion\Core\ServiceMesh\Policy;

use NetFusion\Core\ServiceMesh\Spiffe\SpiffeId;

/**
 * Class MeshPolicy
 *
 * Zero-Trust Service Mesh Access Control Policy.
 * Implements Default-Deny: any traffic not explicitly allowed is rejected.
 */
class MeshPolicy
{
    /** @var MeshRule[] */
    private array $rules = [];

    public function __construct(
        private readonly string $name,
        private readonly string $defaultAction = 'DENY'
    ) {}

    public static function create(string $name): self
    {
        return new self($name);
    }

    public function addRule(MeshRule $rule): self
    {
        $this->rules[] = $rule;
        return $this;
    }

    public function evaluate(
        ServiceIdentity $source,
        SpiffeId $target,
        string $method,
        string $path,
        array $headers = []
    ): AccessDecision {
        foreach ($this->rules as $rule) {
            if ($rule->matches($source, $target, $method, $path, $headers)) {
                if ($rule->getAction() === 'ALLOW') {
                    return AccessDecision::allow($rule->getName(), "Allowed by rule '{$rule->getName()}'");
                }
                return AccessDecision::deny($rule->getName(), "Explicitly denied by rule '{$rule->getName()}'");
            }
        }

        // Zero-Trust Default Deny
        return AccessDecision::deny('', "No matching allow rule in policy '{$this->name}' (Default Deny)");
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return MeshRule[]
     */
    public function getRules(): array
    {
        return $this->rules;
    }
}
