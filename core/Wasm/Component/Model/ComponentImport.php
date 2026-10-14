<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Wasm/Component/Model/ComponentImport.php
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

namespace NetFusion\Core\Wasm\Component\Model;

use NetFusion\Core\Wasm\Component\Wit\Ast\WitFunction;
use NetFusion\Core\Wasm\Component\Wit\Ast\WitInterface;

/**
 * Component Model Import Definition.
 */
class ComponentImport
{
    public function __construct(
        private string $name,
        private WitInterface|WitFunction|string $target,
        private string $kind = 'func'
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTarget(): WitInterface|WitFunction|string
    {
        return $this->target;
    }

    public function getKind(): string
    {
        return $this->kind;
    }
}
