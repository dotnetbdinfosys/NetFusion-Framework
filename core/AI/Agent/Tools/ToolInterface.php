<?php

declare(strict_types=1);

namespace NetFusion\Core\AI\Agent\Tools;

interface ToolInterface
{
    public function getName(): string;
    public function getDescription(): string;
    public function getDefinition(): ToolDefinition;

    /**
     * Execute the tool with LLM-provided arguments.
     *
     * @param array<string, mixed> $arguments
     * @return mixed
     */
    public function execute(array $arguments): mixed;
}
