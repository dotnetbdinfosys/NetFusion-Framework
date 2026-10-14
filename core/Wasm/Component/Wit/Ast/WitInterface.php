<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Wasm/Component/Wit/Ast/WitInterface.php
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
 * Represents a WIT Interface declaration containing types, resources, and functions.
 */
class WitInterface
{
    /** @var array<string, WitTypeDefinition> */
    private array $types = [];
    /** @var array<string, WitFunction> */
    private array $functions = [];

    public function __construct(
        private string $name,
        private ?string $package = null
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPackage(): ?string
    {
        return $this->package;
    }

    public function addType(WitTypeDefinition $type): self
    {
        $this->types[$type->getName()] = $type;
        return $this;
    }

    public function getType(string $name): ?WitTypeDefinition
    {
        return $this->types[$name] ?? null;
    }

    /**
     * @return array<string, WitTypeDefinition>
     */
    public function getTypes(): array
    {
        return $this->types;
    }

    public function addFunction(WitFunction $function): self
    {
        $this->functions[$function->getName()] = $function;
        return $this;
    }

    public function getFunction(string $name): ?WitFunction
    {
        return $this->functions[$name] ?? null;
    }

    /**
     * @return array<string, WitFunction>
     */
    public function getFunctions(): array
    {
        return $this->functions;
    }

    public function toArray(): array
    {
        $typesFormatted = [];
        foreach ($this->types as $name => $type) {
            $typesFormatted[$name] = $type->toArray();
        }

        $funcsFormatted = [];
        foreach ($this->functions as $name => $fn) {
            $funcsFormatted[$name] = $fn->toArray();
        }

        return [
            'name'      => $this->name,
            'package'   => $this->package,
            'types'     => $typesFormatted,
            'functions' => $funcsFormatted,
        ];
    }
}
