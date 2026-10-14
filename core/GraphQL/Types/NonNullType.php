<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/GraphQL/Types/NonNullType.php
| Module    : GraphQL
| Namespace : NetFusion\Core\GraphQL\Types
| Purpose   : Wrapper for Non-Null (!) GraphQL types.
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

use InvalidArgumentException;
use NetFusion\Core\GraphQL\Contracts\TypeInterface;

class NonNullType implements TypeInterface
{
    public function __construct(
        protected TypeInterface $wrappedType
    ) {
        if ($wrappedType instanceof NonNullType) {
            throw new InvalidArgumentException("Cannot nest NonNull inside NonNull.");
        }
    }

    public function getWrappedType(): TypeInterface
    {
        return $this->wrappedType;
    }

    public function getName(): string
    {
        return $this->wrappedType->getName() . '!';
    }

    public function getDescription(): ?string
    {
        return $this->wrappedType->getDescription();
    }

    public function toString(): string
    {
        return $this->wrappedType->toString() . '!';
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
