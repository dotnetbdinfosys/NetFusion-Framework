<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/GraphQL/Contracts/ExecutionContextInterface.php
| Module    : GraphQL
| Namespace : NetFusion\Core\GraphQL\Contracts
| Purpose   : Interface for GraphQL execution context and query metadata.
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
 * Interface ExecutionContextInterface
 *
 * Provides execution state, variables, schema and path information during query resolution.
 *
 * @package NetFusion\Core\GraphQL\Contracts
 */
interface ExecutionContextInterface
{
    /**
     * Get field name being resolved.
     */
    public function getFieldName(): string;

    /**
     * Get current resolution path array (e.g. ['users', 0, 'posts', 1, 'title']).
     *
     * @return array<int|string>
     */
    public function getPath(): array;

    /**
     * Get query variables map.
     *
     * @return array<string, mixed>
     */
    public function getVariables(): array;

    /**
     * Get root value.
     */
    public function getRootValue(): mixed;

    /**
     * Get context value (auth user, DI container, etc.).
     */
    public function getContextValue(): mixed;

    /**
     * Record an execution error.
     */
    public function addError(string $message, array $path = [], ?int $line = null, ?int $column = null): void;

    /**
     * Get recorded errors.
     *
     * @return array<array{message: string, path?: array, locations?: array}>
     */
    public function getErrors(): array;
}
