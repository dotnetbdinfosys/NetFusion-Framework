<?php

declare(strict_types=1);

namespace NetFusion\Core\AI\Agent\Planning;

class AgentThought
{
    public function __construct(
        public readonly int $iteration,
        public readonly string $thought,
        public readonly ?string $action = null,
        public readonly ?array $actionInput = null,
        public ?string $observation = null,
        public readonly ?string $finalAnswer = null
    ) {
    }

    public function getIteration(): int
    {
        return $this->iteration;
    }

    public function getThought(): string
    {
        return $this->thought;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function getActionInput(): ?array
    {
        return $this->actionInput;
    }

    public function getObservation(): ?string
    {
        return $this->observation;
    }

    public function setObservation(string $observation): void
    {
        $this->observation = $observation;
    }

    public function getFinalAnswer(): ?string
    {
        return $this->finalAnswer;
    }

    public function hasAction(): bool
    {
        return $this->action !== null && $this->action !== '';
    }

    public function hasFinalAnswer(): bool
    {
        return $this->finalAnswer !== null && $this->finalAnswer !== '';
    }

    public function toArray(): array
    {
        return [
            'iteration' => $this->iteration,
            'thought' => $this->thought,
            'action' => $this->action,
            'actionInput' => $this->actionInput,
            'observation' => $this->observation,
            'finalAnswer' => $this->finalAnswer,
        ];
    }
}
