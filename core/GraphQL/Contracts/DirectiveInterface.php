<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/GraphQL/Contracts/DirectiveInterface.php
| Module    : GraphQL
| Namespace : NetFusion\Core\GraphQL\Contracts
| Purpose   : Interface for GraphQL directives (@include, @skip, @auth, @deprecated).
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
 * Interface DirectiveInterface
 *
 * Contract for GraphQL directives modifying execution behavior.
 *
 * @package NetFusion\Core\GraphQL\Contracts
 */
interface DirectiveInterface
{
    /**
     * Get directive name without @ prefix (e.g. 'include', 'skip', 'auth').
     */
    public function getName(): string;

    /**
     * Get description.
     */
    public function getDescription(): ?string;

    /**
     * Get supported locations (e.g. ['FIELD', 'FRAGMENT_SPREAD', 'INLINE_FRAGMENT']).
     *
     * @return array<string>
     */
    public function getLocations(): array;

    /**
     * Check if a field or fragment should be included given directive arguments.
     *
     * @param array<string, mixed> $args
     * @param mixed                $context
     * @return bool
     */
    public function shouldInclude(array $args, mixed $context = null): bool;
}
