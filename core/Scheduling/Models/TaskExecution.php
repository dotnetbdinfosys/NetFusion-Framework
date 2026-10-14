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

namespace NetFusion\Core\Scheduling\Models;

class TaskExecution
{
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED  = 'failed';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_TIMEOUT = 'timeout';

    private string $id;
    private string $taskId;
    private string $taskName;
    private string $status;
    private int $exitCode;
    private string $output;
    private float $duration;
    private \DateTimeImmutable $startedAt;
    private ?\DateTimeImmutable $completedAt;
    private ?string $error;
    private int $attempts;

    public function __construct(
        string $id,
        string $taskId,
        string $taskName,
        string $status,
        int $exitCode = 0,
        string $output = '',
        float $duration = 0.0,
        ?\DateTimeImmutable $startedAt = null,
        ?\DateTimeImmutable $completedAt = null,
        ?string $error = null,
        int $attempts = 1
    ) {
        $this->id = $id;
        $this->taskId = $taskId;
        $this->taskName = $taskName;
        $this->status = $status;
        $this->exitCode = $exitCode;
        $this->output = $output;
        $this->duration = $duration;
        $this->startedAt = $startedAt ?? new \DateTimeImmutable();
        $this->completedAt = $completedAt ?? new \DateTimeImmutable();
        $this->error = $error;
        $this->attempts = $attempts;
    }

    public static function success(
        string $taskId,
        string $taskName = '',
        string $output = '',
        float $duration = 0.0,
        int $attempts = 1,
        ?string $id = null
    ): self {
        return new self(
            id: $id ?? uniqid('exec_'),
            taskId: $taskId,
            taskName: $taskName ?: $taskId,
            status: self::STATUS_SUCCESS,
            exitCode: 0,
            output: $output,
            duration: $duration,
            startedAt: new \DateTimeImmutable(),
            completedAt: new \DateTimeImmutable(),
            attempts: $attempts
        );
    }

    public static function failure(
        string $taskId,
        string $taskName = '',
        string $error = '',
        float $duration = 0.0,
        int $attempts = 1,
        int $exitCode = 1,
        string $output = '',
        ?string $id = null
    ): self {
        return new self(
            id: $id ?? uniqid('exec_'),
            taskId: $taskId,
            taskName: $taskName ?: $taskId,
            status: self::STATUS_FAILED,
            exitCode: $exitCode,
            output: $output,
            duration: $duration,
            startedAt: new \DateTimeImmutable(),
            completedAt: new \DateTimeImmutable(),
            error: $error,
            attempts: $attempts
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTaskId(): string
    {
        return $this->taskId;
    }

    public function getTaskName(): string
    {
        return $this->taskName;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getExitCode(): int
    {
        return $this->exitCode;
    }

    public function getOutput(): string
    {
        return $this->output;
    }

    public function getDuration(): float
    {
        return $this->duration;
    }

    public function getDurationMs(): float
    {
        return $this->duration * 1000.0;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function isFailure(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isSkipped(): bool
    {
        return $this->status === self::STATUS_SKIPPED;
    }

    public function isTimeout(): bool
    {
        return $this->status === self::STATUS_TIMEOUT;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->taskId,
            'task_name' => $this->taskName,
            'status' => $this->status,
            'exit_code' => $this->exitCode,
            'output' => $this->output,
            'duration' => $this->duration,
            'started_at' => $this->startedAt->format('Y-m-d H:i:s'),
            'completed_at' => $this->completedAt ? $this->completedAt->format('Y-m-d H:i:s') : null,
            'error' => $this->error,
            'attempts' => $this->attempts,
        ];
    }
}
