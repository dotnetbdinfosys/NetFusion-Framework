<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\AI\Agents;

use JsonSerializable;
use NetFusion\Core\AI\ChatResponse;

class AgentResult implements JsonSerializable
{
    /**
     * @param string $content
     * @param int $steps
     * @param array<array{step: int, tool: string, arguments: array, result: string}> $executedTools
     * @param float $executionTime
     * @param array<string, int> $usage
     * @param ChatResponse|null $lastResponse
     */
    public function __construct(
        protected string $content,
        protected int $steps,
        protected array $executedTools = [],
        protected float $executionTime = 0.0,
        protected array $usage = [],
        protected ?ChatResponse $lastResponse = null
    ) {
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getSteps(): int
    {
        return $this->steps;
    }

    /**
     * @return array<array{step: int, tool: string, arguments: array, result: string}>
     */
    public function getExecutedTools(): array
    {
        return $this->executedTools;
    }

    public function getExecutionTime(): float
    {
        return $this->executionTime;
    }

    /**
     * @return array<string, int>
     */
    public function getUsage(): array
    {
        return $this->usage;
    }

    public function getLastResponse(): ?ChatResponse
    {
        return $this->lastResponse;
    }

    public function __toString(): string
    {
        return $this->content;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'steps' => $this->steps,
            'executed_tools' => $this->executedTools,
            'execution_time' => $this->executionTime,
            'usage' => $this->usage,
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
