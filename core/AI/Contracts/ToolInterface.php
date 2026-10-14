<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/AI/Contracts/ToolInterface.php
| Module    : AI
| Namespace : NetFusion\Core\AI\Contracts
| Purpose   : Contract for callable agent tools and functions.
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

namespace NetFusion\Core\AI\Contracts;

interface ToolInterface
{
    /**
     * Get the unique tool name (e.g., 'calculator', 'search_database').
     */
    public function getName(): string;

    /**
     * Get human-readable description for LLM tool selection.
     */
    public function getDescription(): string;

    /**
     * Get JSON Schema for tool parameters.
     *
     * @return array<string, mixed>
     */
    public function getParametersSchema(): array;

    /**
     * Execute the tool with provided arguments.
     *
     * @param array<string, mixed> $arguments
     * @return mixed
     */
    public function execute(array $arguments): mixed;

    /**
     * Export tool definition in standard OpenAI function format.
     *
     * @return array<string, mixed>
     */
    public function toFunctionDefinition(): array;
}
