<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/GraphQL/Types/Argument.php
| Module    : GraphQL
| Namespace : NetFusion\Core\GraphQL\Types
| Purpose   : Definition of a field or directive input argument.
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

namespace NetFusion\Core\GraphQL\Types;

use NetFusion\Core\GraphQL\Contracts\TypeInterface;

class Argument
{
    public function __construct(
        protected string $name,
        protected TypeInterface $type,
        protected ?string $description = null,
        protected mixed $defaultValue = null,
        protected bool $hasDefaultValue = false
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): TypeInterface
    {
        return $this->type;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getDefaultValue(): mixed
    {
        return $this->defaultValue;
    }

    public function hasDefaultValue(): bool
    {
        return $this->hasDefaultValue;
    }
}
