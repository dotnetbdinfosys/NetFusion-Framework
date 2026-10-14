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

namespace NetFusion\Core\Wasm\Types;

/**
 * WebAssembly function signature representing parameters and returns.
 */
class FunctionType
{
    /**
     * @param array<ValueType> $params
     * @param array<ValueType> $returns
     */
    public function __construct(
        public readonly array $params = [],
        public readonly array $returns = []
    ) {
    }

    public function getParamCount(): int
    {
        return count($this->params);
    }

    public function getReturnCount(): int
    {
        return count($this->returns);
    }

    public function toString(): string
    {
        $paramStrs = array_map(fn(ValueType $t) => $t->typeName(), $this->params);
        $returnStrs = array_map(fn(ValueType $t) => $t->typeName(), $this->returns);

        return '(' . implode(', ', $paramStrs) . ') -> (' . implode(', ', $returnStrs) . ')';
    }
}
