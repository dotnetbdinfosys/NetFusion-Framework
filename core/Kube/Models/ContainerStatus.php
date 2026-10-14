<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\Kube\Models;

use NetFusion\Core\Kube\Enums\ContainerState;

/**
 * Tracks the live status of an active container inside a Pod.
 */
class ContainerStatus
{
    /**
     * @var array<string, int> Consecutive probe failures keyed by probe type (startup, liveness, readiness)
     */
    protected array $probeFailures = [];

    /**
     * @var array<string, int> Consecutive probe successes keyed by probe type
     */
    protected array $probeSuccesses = [];

    public function __construct(
        protected string $name,
        protected ContainerState $state = ContainerState::WAITING,
        protected bool $ready = false,
        protected int $restartCount = 0,
        protected ?int $exitCode = null,
        protected ?string $reason = null,
        protected ?string $message = null,
        protected ?float $startedAt = null
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getState(): ContainerState
    {
        return $this->state;
    }

    public function setState(ContainerState $state): self
    {
        $this->state = $state;
        return $this;
    }

    public function isReady(): bool
    {
        return $this->ready;
    }

    public function setReady(bool $ready): self
    {
        $this->ready = $ready;
        return $this;
    }

    public function getRestartCount(): int
    {
        return $this->restartCount;
    }

    public function incrementRestartCount(): self
    {
        $this->restartCount++;
        return $this;
    }

    public function getExitCode(): ?int
    {
        return $this->exitCode;
    }

    public function setExitCode(?int $exitCode): self
    {
        $this->exitCode = $exitCode;
        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): self
    {
        $this->reason = $reason;
        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function getProbeFailures(string $type = 'liveness'): int
    {
        return $this->probeFailures[$type] ?? 0;
    }

    public function incrementProbeFailures(string $type = 'liveness'): self
    {
        $this->probeFailures[$type] = ($this->probeFailures[$type] ?? 0) + 1;
        $this->probeSuccesses[$type] = 0;
        return $this;
    }

    public function getProbeSuccesses(string $type = 'liveness'): int
    {
        return $this->probeSuccesses[$type] ?? 0;
    }

    public function incrementProbeSuccesses(string $type = 'liveness'): self
    {
        $this->probeSuccesses[$type] = ($this->probeSuccesses[$type] ?? 0) + 1;
        $this->probeFailures[$type] = 0;
        return $this;
    }

    public function resetProbeCounters(?string $type = null): self
    {
        if ($type !== null) {
            $this->probeFailures[$type] = 0;
            $this->probeSuccesses[$type] = 0;
        } else {
            $this->probeFailures = [];
            $this->probeSuccesses = [];
        }
        return $this;
    }

    public function getConsecutiveProbeFailures(string $type = 'liveness'): int
    {
        return $this->getProbeFailures($type);
    }

    public function getConsecutiveProbeSuccesses(string $type = 'liveness'): int
    {
        return $this->getProbeSuccesses($type);
    }

    public function getStartedAt(): ?float
    {
        return $this->startedAt;
    }

    public function setStartedAt(?float $startedAt): self
    {
        $this->startedAt = $startedAt;
        return $this;
    }
}
