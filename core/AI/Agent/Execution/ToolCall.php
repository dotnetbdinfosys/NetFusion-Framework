<?php

declare(strict_types=1);

namespace NetFusion\Core\AI\Agent\Execution;

class ToolCall
{
    /**
     * @param string $id
     * @param string $toolName
     * @param array<string, mixed> $arguments
     * @param string $rawArguments
     */
    public function __construct(
        private readonly string $id,
        private readonly string $toolName,
        private readonly array $arguments,
        private readonly string $rawArguments = ''
    ) {
    }

    public static function fromArray(array $data): self
    {
        $id = (string) ($data['id'] ?? uniqid('call_', true));
        $name = (string) ($data['name'] ?? ($data['function']['name'] ?? ''));
        $rawArgs = (string) ($data['arguments'] ?? ($data['function']['arguments'] ?? '{}'));
        $parsedArgs = is_array($rawArgs) ? $rawArgs : (json_decode($rawArgs, true) ?? []);

        return new self($id, $name, $parsedArgs, is_string($rawArgs) ? $rawArgs : json_encode($rawArgs));
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getToolName(): string
    {
        return $this->toolName;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function getRawArguments(): string
    {
        return $this->rawArguments;
    }
}
