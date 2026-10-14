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

namespace NetFusion\Core\IaC\Graph;

use NetFusion\Core\IaC\Ast\AstBlock;
use NetFusion\Core\IaC\State\StateResource;

/**
 * Node in the infrastructure DAG holding desired AST block and current state.
 */
class ResourceNode
{
    /**
     * @var array<string> Addresses this resource depends on (must be created first)
     */
    public array $dependencies = [];

    /**
     * @var array<string> Addresses that depend on this resource
     */
    public array $dependents = [];

    public function __construct(
        public readonly string $address,
        public readonly ?AstBlock $astBlock = null,
        public readonly ?StateResource $stateResource = null,
        array $dependencies = []
    ) {
        $this->dependencies = $dependencies;
    }

    public function addDependency(string $address): void
    {
        if (!in_array($address, $this->dependencies, true)) {
            $this->dependencies[] = $address;
        }
    }

    public function addDependent(string $address): void
    {
        if (!in_array($address, $this->dependents, true)) {
            $this->dependents[] = $address;
        }
    }
}
