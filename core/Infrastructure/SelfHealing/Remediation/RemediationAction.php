<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Infrastructure/SelfHealing/Remediation/RemediationAction.php
 | Author    : Md. Atiquzzaman Ashish
 | Company   : DotNetBD
 | Website   : www.dotnetbd.com
 | Copyright : © DotNetBD. All rights reserved.
 | License   : MIT
 | Version   : 1.0.0
 |
 |--------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace NetFusion\Core\Infrastructure\SelfHealing\Remediation;

use NetFusion\Core\Infrastructure\SelfHealing\Enums\ActionType;
use NetFusion\Core\Infrastructure\SelfHealing\Enums\RemediationStatus;

/**
 * Concrete remediation action targeting a specific infrastructure service.
 */
class RemediationAction
{
    private array $executionLogs = [];

    public function __construct(
        private string $id,
        private string $targetService,
        private ActionType $actionType,
        private array $parameters = [],
        private float $canaryPercentage = 0.0,
        private RemediationStatus $status = RemediationStatus::PENDING,
        private int $createdAt = 0
    ) {
        if ($this->createdAt === 0) {
            $this->createdAt = time();
        }
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTargetService(): string
    {
        return $this->targetService;
    }

    public function getActionType(): ActionType
    {
        return $this->actionType;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getCanaryPercentage(): float
    {
        return $this->canaryPercentage;
    }

    public function setCanaryPercentage(float $percentage): self
    {
        $this->canaryPercentage = $percentage;
        return $this;
    }

    public function getStatus(): RemediationStatus
    {
        return $this->status;
    }

    public function setStatus(RemediationStatus $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    public function addLog(string $message): self
    {
        $this->executionLogs[] = [
            'time'    => microtime(true),
            'message' => $message,
            'percent' => $this->canaryPercentage,
            'status'  => $this->status->value,
        ];
        return $this;
    }

    public function getLogs(): array
    {
        return $this->executionLogs;
    }

    public function toArray(): array
    {
        return [
            'id'             => $this->id,
            'target_service' => $this->targetService,
            'action_type'    => $this->actionType->value,
            'parameters'     => $this->parameters,
            'canary_pct'     => $this->canaryPercentage,
            'status'         => $this->status->value,
            'logs_count'     => count($this->executionLogs),
            'created_at'     => $this->createdAt,
        ];
    }
}
