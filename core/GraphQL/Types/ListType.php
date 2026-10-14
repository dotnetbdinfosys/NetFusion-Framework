<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/GraphQL/Types/ListType.php
| Module    : GraphQL
| Namespace : NetFusion\Core\GraphQL\Types
| Purpose   : Wrapper for List ([...]) GraphQL types.
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

class ListType implements TypeInterface
{
    public function __construct(
        protected TypeInterface $itemType
    ) {}

    public function getItemType(): TypeInterface
    {
        return $this->itemType;
    }

    public function getName(): string
    {
        return '[' . $this->itemType->getName() . ']';
    }

    public function getDescription(): ?string
    {
        return $this->itemType->getDescription();
    }

    public function toString(): string
    {
        return '[' . $this->itemType->toString() . ']';
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
