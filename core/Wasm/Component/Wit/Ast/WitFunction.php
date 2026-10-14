<?php

/*
 |--------------------------------------------------------------------------
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Wasm/Component/Wit/Ast/WitFunction.php
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

namespace NetFusion\Core\Wasm\Component\Wit\Ast;

/**
 * Represents a WIT Function signature in an interface or world.
 */
class WitFunction
{
    /**
     * @param string $name Function identifier
     * @param array<string, string|WitTypeDefinition> $params Named parameters [paramName => type]
     * @param string|WitTypeDefinition|null $returnType Return type, or null for void
     */
    public function __construct(
        private string $name,
        private array $params = [],
        private string|WitTypeDefinition|null $returnType = null
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array<string, string|WitTypeDefinition>
     */
    public function getParams(): array
    {
        return $this->params;
    }

    public function getReturnType(): string|WitTypeDefinition|null
    {
        return $this->returnType;
    }

    public function getReturnTypeName(): ?string
    {
        if ($this->returnType === null) {
            return null;
        }
        return $this->returnType instanceof WitTypeDefinition
            ? $this->returnType->getTypeName()
            : (string)$this->returnType;
    }

    public function toArray(): array
    {
        $paramsFormatted = [];
        foreach ($this->params as $paramName => $type) {
            $paramsFormatted[$paramName] = $type instanceof WitTypeDefinition ? $type->toArray() : (string)$type;
        }

        return [
            'name'       => $this->name,
            'params'     => $paramsFormatted,
            'returnType' => $this->getReturnTypeName(),
        ];
    }
}
