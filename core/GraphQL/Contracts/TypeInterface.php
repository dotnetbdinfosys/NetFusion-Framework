<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/GraphQL/Contracts/TypeInterface.php
| Module    : GraphQL
| Namespace : NetFusion\Core\GraphQL\Contracts
| Purpose   : Interface for all GraphQL Type system definitions.
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

namespace NetFusion\Core\GraphQL\Contracts;

/**
 * Interface TypeInterface
 *
 * Base contract for all GraphQL types (Scalars, Objects, Lists, NonNull, Enums, etc.).
 *
 * @package NetFusion\Core\GraphQL\Contracts
 */
interface TypeInterface
{
    /**
     * Get the name of the GraphQL type.
     */
    public function getName(): string;

    /**
     * Get the description of the GraphQL type.
     */
    public function getDescription(): ?string;

    /**
     * Get string representation of type (e.g. "String", "[User]!", "Int!").
     */
    public function toString(): string;
}
