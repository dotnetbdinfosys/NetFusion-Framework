<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/GraphQL/Contracts/ResolverInterface.php
| Module    : GraphQL
| Namespace : NetFusion\Core\GraphQL\Contracts
| Purpose   : Interface for GraphQL field resolvers.
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
 * Interface ResolverInterface
 *
 * Contract for resolving a GraphQL field value during execution.
 *
 * @package NetFusion\Core\GraphQL\Contracts
 */
interface ResolverInterface
{
    /**
     * Resolve the field value.
     *
     * @param mixed                        $source      Parent object/array resolved value.
     * @param array<string, mixed>         $args        Input arguments passed to the field.
     * @param mixed                        $context     Application / request context (e.g. Auth, DB).
     * @param ExecutionContextInterface    $info        Execution information and AST metadata.
     * @return mixed
     */
    public function resolve(mixed $source, array $args, mixed $context, ExecutionContextInterface $info): mixed;
}
