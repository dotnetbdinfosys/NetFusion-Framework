<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Wasm/Component/Wit/Ast/WitWorld.php
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
 * Represents a WIT World defining component boundaries (imports and exports).
 */
class WitWorld
{
    /** @var array<string, WitInterface|WitFunction|string> */
    private array $imports = [];
    /** @var array<string, WitInterface|WitFunction|string> */
    private array $exports = [];

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

    public function addImport(string $name, WitInterface|WitFunction|string $target): self
    {
        $this->imports[$name] = $target;
        return $this;
    }

    public function addExport(string $name, WitInterface|WitFunction|string $target): self
    {
        $this->exports[$name] = $target;
        return $this;
    }

    public function getImports(): array
    {
        return $this->imports;
    }

    public function getExports(): array
    {
        return $this->exports;
    }

    public function toArray(): array
    {
        $fmt = function (array $items): array {
            $res = [];
            foreach ($items as $k => $v) {
                if ($v instanceof WitInterface || $v instanceof WitFunction) {
                    $res[$k] = $v->toArray();
                } else {
                    $res[$k] = (string)$v;
                }
            }
            return $res;
        };

        return [
            'name'    => $this->name,
            'package' => $this->package,
            'imports' => $fmt($this->imports),
            'exports' => $fmt($this->exports),
        ];
    }
}
