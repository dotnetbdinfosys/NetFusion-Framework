<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
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

namespace NetFusion\Core\DisasterRecovery\Drill;

use JsonSerializable;

/**
 * Class DrillExecution
 *
 * Tracks the execution, measured RTO/RPO metrics, and SLA compliance of a DR drill.
 */
class DrillExecution implements JsonSerializable
{
    public const TYPE_DRILL    = 'DRILL';
    public const TYPE_FAILOVER = 'FAILOVER';

    public const STATUS_RUNNING = 'RUNNING';
    public const STATUS_SUCCESS = 'SUCCESS';
    public const STATUS_FAILED  = 'FAILED';

    public float $endTime = 0.0;
    public float $rtoSeconds = 0.0;
    public float $rpoSeconds = 0.0;
    public string $status = self::STATUS_RUNNING;
    public array $steps = [];

    public function __construct(
        public readonly string $drillId,
        public readonly string $type,
        public readonly string $targetRegion,
        public readonly float $startTime,
        public readonly float $targetRtoSla = 60.0,
        public readonly float $targetRpoSla = 5.0
    ) {
    }

    public function addStep(string $stepName, bool $success, string $message = ''): void
    {
        $this->steps[] = [
            'step'      => $stepName,
            'success'   => $success,
            'message'   => $message,
            'timestamp' => microtime(true),
        ];

        if (!$success) {
            $this->status = self::STATUS_FAILED;
        }
    }

    public function complete(float $rpoSeconds = 0.0, ?float $now = null): self
    {
        $this->endTime = $now ?? microtime(true);
        $this->rtoSeconds = max(0.001, round($this->endTime - $this->startTime, 4));
        $this->rpoSeconds = max(0.0, round($rpoSeconds, 4));

        if ($this->status !== self::STATUS_FAILED) {
            $this->status = self::STATUS_SUCCESS;
        }

        return $this;
    }

    public function isSlaCompliant(): bool
    {
        return $this->status === self::STATUS_SUCCESS
            && $this->rtoSeconds <= $this->targetRtoSla
            && $this->rpoSeconds <= $this->targetRpoSla;
    }

    public function toArray(): array
    {
        return [
            'drill_id'          => $this->drillId,
            'type'              => $this->type,
            'target_region'     => $this->targetRegion,
            'status'            => $this->status,
            'start_time'        => $this->startTime,
            'end_time'          => $this->endTime,
            'rto_seconds'       => $this->rtoSeconds,
            'rpo_seconds'       => $this->rpoSeconds,
            'target_rto_sla'    => $this->targetRtoSla,
            'target_rpo_sla'    => $this->targetRpoSla,
            'is_sla_compliant'  => $this->isSlaCompliant(),
            'step_count'        => count($this->steps),
            'steps'             => $this->steps,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
