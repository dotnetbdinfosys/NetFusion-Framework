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

namespace NetFusion\Core\Saga;

use JsonSerializable;

class SagaInstance implements JsonSerializable
{
    /**
     * @param string $id
     * @param string $sagaName
     * @param SagaState $state
     * @param SagaContext $context
     * @param int $currentStepIndex
     * @param SagaStepResult[] $stepResults
     * @param string|null $error
     * @param float $startedAt
     * @param float|null $completedAt
     */
    public function __construct(
        public string $id,
        public string $sagaName,
        public SagaState $state = SagaState::PENDING,
        public SagaContext $context = new SagaContext(),
        public int $currentStepIndex = 0,
        public array $stepResults = [],
        public ?string $error = null,
        public float $startedAt = 0.0,
        public ?float $completedAt = null
    ) {
        if ($this->startedAt <= 0.0) {
            $this->startedAt = microtime(true);
        }
    }

    public static function create(string $sagaName, array $payload = []): self
    {
        return new self(
            id: uniqid('saga_', true),
            sagaName: $sagaName,
            state: SagaState::PENDING,
            context: new SagaContext($payload),
            startedAt: microtime(true)
        );
    }

    public function recordStepResult(SagaStepResult $result): self
    {
        $this->stepResults[$result->stepName] = $result;
        return $this;
    }

    public function getStepResult(string $stepName): ?SagaStepResult
    {
        return $this->stepResults[$stepName] ?? null;
    }

    public function isSuccess(): bool
    {
        return $this->state === SagaState::COMPLETED;
    }

    public function isCompensated(): bool
    {
        return $this->state === SagaState::COMPENSATED;
    }

    public function isFailed(): bool
    {
        return $this->state === SagaState::FAILED;
    }

    public function getExecutionDurationMs(): float
    {
        $end = $this->completedAt ?? microtime(true);
        return round(($end - $this->startedAt) * 1000, 2);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'saga_name' => $this->sagaName,
            'state' => $this->state->value,
            'context' => $this->context->all(),
            'current_step_index' => $this->currentStepIndex,
            'step_results' => array_map(fn(SagaStepResult $r) => $r->toArray(), $this->stepResults),
            'error' => $this->error,
            'started_at' => $this->startedAt,
            'completed_at' => $this->completedAt,
            'duration_ms' => $this->getExecutionDurationMs(),
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
