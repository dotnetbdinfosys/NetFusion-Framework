<?php

declare(strict_types=1);

namespace NetFusion\Core\AI\Agent\Planning;

class AgentResult
{
    /**
     * @param string $task
     * @param string $finalAnswer
     * @param AgentThought[] $thoughts
     * @param int $iterations
     * @param bool $isSuccess
     * @param float $totalDurationMs
     * @param int $toolCallsCount
     */
    public readonly float $durationMs;

    public function __construct(
        public readonly string $task,
        public readonly string $finalAnswer,
        public readonly array $thoughts = [],
        public readonly int $iterations = 0,
        public readonly bool $isSuccess = true,
        public readonly float $totalDurationMs = 0.0,
        public readonly int $toolCallsCount = 0
    ) {
        $this->durationMs = $totalDurationMs;
    }

    public function getTask(): string
    {
        return $this->task;
    }

    public function getFinalAnswer(): string
    {
        return $this->finalAnswer;
    }

    /**
     * @return AgentThought[]
     */
    public function getThoughts(): array
    {
        return $this->thoughts;
    }

    public function getIterations(): int
    {
        return $this->iterations;
    }

    public function isSuccess(): bool
    {
        return $this->isSuccess;
    }

    public function getTotalDurationMs(): float
    {
        return $this->totalDurationMs;
    }

    public function getToolCallsCount(): int
    {
        return $this->toolCallsCount;
    }

    public function toArray(): array
    {
        return [
            'task' => $this->task,
            'finalAnswer' => $this->finalAnswer,
            'isSuccess' => $this->isSuccess,
            'iterations' => $this->iterations,
            'toolCallsCount' => $this->toolCallsCount,
            'totalDurationMs' => $this->totalDurationMs,
            'thoughts' => array_map(fn(AgentThought $t) => $t->toArray(), $this->thoughts),
        ];
    }
}
