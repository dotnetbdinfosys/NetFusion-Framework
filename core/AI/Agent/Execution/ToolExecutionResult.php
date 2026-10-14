<?php

declare(strict_types=1);

namespace NetFusion\Core\AI\Agent\Execution;

class ToolExecutionResult
{
    public function __construct(
        public readonly string $toolCallId,
        public readonly string $toolName,
        public readonly mixed $result,
        public readonly bool $isError = false,
        public readonly float $durationMs = 0.0,
        public readonly ?string $errorMessage = null
    ) {
    }

    public function getToolCallId(): string
    {
        return $this->toolCallId;
    }

    public function getToolName(): string
    {
        return $this->toolName;
    }

    public function getResult(): mixed
    {
        return $this->result;
    }

    public function isError(): bool
    {
        return $this->isError;
    }

    public function getDurationMs(): float
    {
        return $this->durationMs;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function toObservationString(): string
    {
        if ($this->isError) {
            return "Error executing tool '{$this->toolName}': " . ($this->errorMessage ?? 'Unknown error');
        }

        if (is_string($this->result)) {
            return $this->result;
        }

        if (is_scalar($this->result)) {
            return (string) $this->result;
        }

        return json_encode($this->result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }
}
