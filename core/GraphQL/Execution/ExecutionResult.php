<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/GraphQL/Execution/ExecutionResult.php
| Module    : GraphQL
| Namespace : NetFusion\Core\GraphQL\Execution
| Purpose   : GraphQL execution response container (data & errors).
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

use JsonSerializable;

class ExecutionResult implements JsonSerializable
{
    /**
     * @param array<string, mixed>|null $data
     * @param array<array{message: string, path?: array, locations?: array}> $errors
     * @param array<string, mixed>      $extensions
     */
    public function __construct(
        public ?array $data = null,
        public array $errors = [],
        public array $extensions = []
    ) {}

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function isSuccess(): bool
    {
        return empty($this->errors);
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function toArray(): array
    {
        $result = [];
        if ($this->data !== null) {
            $result['data'] = $this->data;
        }
        if (!empty($this->errors)) {
            $result['errors'] = $this->errors;
        }
        if (!empty($this->extensions)) {
            $result['extensions'] = $this->extensions;
        }

        return $result;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toJson(int $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES): string
    {
        return (string) json_encode($this->toArray(), $flags);
    }
}
