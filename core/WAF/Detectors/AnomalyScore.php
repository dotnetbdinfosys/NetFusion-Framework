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

namespace NetFusion\Core\WAF\Detectors;

use JsonSerializable;

class AnomalyScore implements JsonSerializable
{
    protected float $score = 0.0;

    /**
     * @var array<array{rule_id: string, detector: string, field: string, score: float, match: string, message: string}>
     */
    protected array $matches = [];

    /**
     * @param float $warningThreshold Anomaly score threshold to issue a warning/challenge
     * @param float $blockingThreshold Anomaly score threshold to block the request
     */
    public function __construct(
        protected float $warningThreshold = 3.0,
        protected float $blockingThreshold = 5.0
    ) {
    }

    /**
     * Add anomaly score points for a detected rule match.
     */
    public function addMatch(
        string $ruleId,
        string $detector,
        string $field,
        float $points,
        string $matchSnippet = '',
        string $message = ''
    ): void {
        $this->score += $points;
        $this->matches[] = [
            'rule_id' => $ruleId,
            'detector' => $detector,
            'field' => $field,
            'score' => $points,
            'match' => $matchSnippet,
            'message' => $message,
        ];
    }

    public function getScore(): float
    {
        return $this->score;
    }

    public function getMatches(): array
    {
        return $this->matches;
    }

    public function hasMatches(): bool
    {
        return !empty($this->matches);
    }

    public function isWarning(): bool
    {
        return $this->score >= $this->warningThreshold && $this->score < $this->blockingThreshold;
    }

    public function isBlocked(): bool
    {
        return $this->score >= $this->blockingThreshold;
    }

    public function getWarningThreshold(): float
    {
        return $this->warningThreshold;
    }

    public function getBlockingThreshold(): float
    {
        return $this->blockingThreshold;
    }

    public function jsonSerialize(): array
    {
        return [
            'score' => $this->score,
            'is_blocked' => $this->isBlocked(),
            'is_warning' => $this->isWarning(),
            'matches' => $this->matches,
        ];
    }
}
