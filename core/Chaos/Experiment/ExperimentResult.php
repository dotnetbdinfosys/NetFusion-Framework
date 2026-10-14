<?php

declare(strict_types=1);

namespace NetFusion\Core\Chaos\Experiment;

/**
 * Class ExperimentResult
 *
 * Encapsulates the complete telemetry and outcome of a Chaos Experiment run.
 */
class ExperimentResult
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_SUCCESS = 'SUCCESS';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_ABORTED = 'ABORTED';
    public const STATUS_BLAST_RADIUS_VIOLATION = 'BLAST_RADIUS_VIOLATION';

    protected string $experimentId;
    protected string $experimentName;
    protected string $status = self::STATUS_PENDING;
    protected bool $steadyStatePre = false;
    protected bool $steadyStatePost = false;
    protected array $violations = [];
    protected array $injectedFaults = [];
    protected float $resiliencyScore = 0.0;
    protected float $startTime;
    protected ?float $endTime = null;
    protected float $durationMs = 0.0;
    protected mixed $actionResult = null;
    protected ?string $abortReason = null;
    protected array $logs = [];

    public function __construct(string $experimentId, string $experimentName)
    {
        $this->experimentId = $experimentId;
        $this->experimentName = $experimentName;
        $this->startTime = microtime(true);
    }

    public function getExperimentId(): string
    {
        return $this->experimentId;
    }

    public function getExperimentName(): string
    {
        return $this->experimentName;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function isSteadyStatePre(): bool
    {
        return $this->steadyStatePre;
    }

    public function setSteadyStatePre(bool $pre): static
    {
        $this->steadyStatePre = $pre;
        return $this;
    }

    public function isSteadyStatePost(): bool
    {
        return $this->steadyStatePost;
    }

    public function setSteadyStatePost(bool $post): static
    {
        $this->steadyStatePost = $post;
        return $this;
    }

    public function getViolations(): array
    {
        return $this->violations;
    }

    public function addViolation(string $violation): static
    {
        $this->violations[] = $violation;
        return $this;
    }

    public function setViolations(array $violations): static
    {
        $this->violations = $violations;
        return $this;
    }

    public function getInjectedFaults(): array
    {
        return $this->injectedFaults;
    }

    public function recordFault(string $injectorName, mixed $details): static
    {
        $this->injectedFaults[] = [
            'injector' => $injectorName,
            'details' => $details,
            'timestamp' => microtime(true),
        ];
        return $this;
    }

    public function getResiliencyScore(): float
    {
        return $this->resiliencyScore;
    }

    public function setResiliencyScore(float $score): static
    {
        $this->resiliencyScore = max(0.0, min(100.0, round($score, 2)));
        return $this;
    }

    public function getStartTime(): float
    {
        return $this->startTime;
    }

    public function getEndTime(): ?float
    {
        return $this->endTime;
    }

    public function getDurationMs(): float
    {
        return $this->durationMs;
    }

    public function complete(): static
    {
        $this->endTime = microtime(true);
        $this->durationMs = round(($this->endTime - $this->startTime) * 1000, 2);
        return $this;
    }

    public function getActionResult(): mixed
    {
        return $this->actionResult;
    }

    public function setActionResult(mixed $result): static
    {
        $this->actionResult = $result;
        return $this;
    }

    public function getAbortReason(): ?string
    {
        return $this->abortReason;
    }

    public function setAbortReason(?string $reason): static
    {
        $this->abortReason = $reason;
        return $this;
    }

    public function log(string $message): static
    {
        $this->logs[] = [
            'time' => date('Y-m-d H:i:s.') . sprintf('%03d', (int) ((microtime(true) - floor(microtime(true))) * 1000)),
            'message' => $message,
        ];
        return $this;
    }

    public function getLogs(): array
    {
        return $this->logs;
    }

    public function toArray(): array
    {
        return [
            'experiment_id' => $this->experimentId,
            'experiment_name' => $this->experimentName,
            'status' => $this->status,
            'resiliency_score' => $this->resiliencyScore,
            'steady_state_pre' => $this->steadyStatePre,
            'steady_state_post' => $this->steadyStatePost,
            'violations_count' => count($this->violations),
            'violations' => $this->violations,
            'injected_faults_count' => count($this->injectedFaults),
            'injected_faults' => $this->injectedFaults,
            'duration_ms' => $this->durationMs,
            'abort_reason' => $this->abortReason,
            'logs_count' => count($this->logs),
        ];
    }

    public function toJson(int $options = JSON_PRETTY_PRINT): string
    {
        return json_encode($this->toArray(), $options);
    }
}
