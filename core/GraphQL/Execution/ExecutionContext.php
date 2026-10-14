<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/GraphQL/Execution/ExecutionContext.php
| Module    : GraphQL
| Namespace : NetFusion\Core\GraphQL\Execution
| Purpose   : Execution state, path, variables and error collector for query resolution.
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

namespace NetFusion\Core\GraphQL\Execution;

use NetFusion\Core\GraphQL\Contracts\ExecutionContextInterface;
use NetFusion\Core\GraphQL\Types\Schema;

class ExecutionContext implements ExecutionContextInterface
{
    protected string $fieldName = '';
    protected array $path = [];
    protected array $errors = [];

    /**
     * @param Schema               $schema
     * @param array<string, mixed> $variables
     * @param mixed                $rootValue
     * @param mixed                $contextValue
     */
    public function __construct(
        public readonly Schema $schema,
        public readonly array $variables = [],
        public readonly mixed $rootValue = null,
        public readonly mixed $contextValue = null
    ) {}

    public function setFieldName(string $fieldName): void
    {
        $this->fieldName = $fieldName;
    }

    public function getFieldName(): string
    {
        return $this->fieldName;
    }

    public function setPath(array $path): void
    {
        $this->path = $path;
    }

    public function getPath(): array
    {
        return $this->path;
    }

    public function getVariables(): array
    {
        return $this->variables;
    }

    public function getRootValue(): mixed
    {
        return $this->rootValue;
    }

    public function getContextValue(): mixed
    {
        return $this->contextValue;
    }

    public function addError(string $message, array $path = [], ?int $line = null, ?int $column = null): void
    {
        $error = ['message' => $message];
        $effectivePath = !empty($path) ? $path : $this->path;
        if (!empty($effectivePath)) {
            $error['path'] = $effectivePath;
        }
        if ($line !== null && $column !== null) {
            $error['locations'] = [['line' => $line, 'column' => $column]];
        }
        $this->errors[] = $error;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
