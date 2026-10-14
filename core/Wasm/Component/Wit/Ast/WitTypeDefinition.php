<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Wasm/Component/Wit/Ast/WitTypeDefinition.php
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

use NetFusion\Core\Wasm\Component\Enums\WitType;

/**
 * Represents a WIT Type (primitive or defined composite type like record, variant, list, etc.).
 */
class WitTypeDefinition
{
    /**
     * @param string $name
     * @param WitType|string $type
     * @param array<string, mixed> $details Fields for record, cases for variant/enum, inner type for list/option
     */
    public function __construct(
        private string $name,
        private WitType|string $type,
        private array $details = []
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): WitType|string
    {
        return $this->type;
    }

    public function getTypeName(): string
    {
        return $this->type instanceof WitType ? $this->type->value : (string)$this->type;
    }

    public function getDetails(): array
    {
        return $this->details;
    }

    public function isPrimitive(): bool
    {
        return $this->type instanceof WitType && !$this->type->isCompound();
    }

    public function toArray(): array
    {
        return [
            'name'    => $this->name,
            'type'    => $this->getTypeName(),
            'details' => $this->details,
        ];
    }
}
