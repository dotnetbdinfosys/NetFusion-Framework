<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Infrastructure/SelfHealing/Analysis/RootCauseReport.php
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

namespace NetFusion\Core\Infrastructure\SelfHealing\Analysis;

use NetFusion\Core\Infrastructure\SelfHealing\Enums\ActionType;
use NetFusion\Core\Infrastructure\SelfHealing\Enums\IncidentSeverity;

/**
 * Diagnostic Root-Cause Analysis Report.
 */
class RootCauseReport
{
    public function __construct(
        private string $reportId,
        private string $primaryCulpritService,
        private string $primaryHypothesis,
        private float $confidence,
        private array $causalChain,
        private array $hypotheses,
        private ActionType $recommendedAction,
        private IncidentSeverity $severity,
        private int $timestamp = 0
    ) {
        if ($this->timestamp === 0) {
            $this->timestamp = time();
        }
    }

    public function getReportId(): string
    {
        return $this->reportId;
    }

    public function getPrimaryCulpritService(): string
    {
        return $this->primaryCulpritService;
    }

    public function getPrimaryHypothesis(): string
    {
        return $this->primaryHypothesis;
    }

    public function getConfidence(): float
    {
        return $this->confidence;
    }

    /**
     * @return string[]
     */
    public function getCausalChain(): array
    {
        return $this->causalChain;
    }

    /**
     * @return array<string, float>
     */
    public function getHypotheses(): array
    {
        return $this->hypotheses;
    }

    public function getRecommendedAction(): ActionType
    {
        return $this->recommendedAction;
    }

    public function getSeverity(): IncidentSeverity
    {
        return $this->severity;
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    public function toArray(): array
    {
        return [
            'report_id'          => $this->reportId,
            'culprit_service'    => $this->primaryCulpritService,
            'primary_hypothesis' => $this->primaryHypothesis,
            'confidence'         => $this->confidence,
            'causal_chain'       => $this->causalChain,
            'hypotheses'         => $this->hypotheses,
            'recommended_action' => $this->recommendedAction->value,
            'severity'           => $this->severity->value,
            'timestamp'          => $this->timestamp,
        ];
    }
}
