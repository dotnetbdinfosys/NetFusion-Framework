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

namespace NetFusion\Core\Experimentation\Canary;

use NetFusion\Core\Experimentation\EvaluationContext;
use NetFusion\Core\Experimentation\Segment\Segment;

class CanaryRollout
{
    /**
     * @param string         $flagKey                Unique feature flag / canary identifier
     * @param int            $percentage             Current rollout percentage (0 - 100)
     * @param array<int, mixed> $stages              Multi-stage rollout ramp steps [['percentage' => 10, 'after_timestamp' => 1700000000], ...]
     * @param Segment|null   $segment                Optional targeting segment constraint
     * @param float          $errorThresholdPercent  Automatic kill switch if error rate exceeds threshold
     * @param bool           $killSwitchTriggered    If true, immediately shuts down canary
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $flagKey,
        public int $percentage = 0,
        public array $stages = [],
        public ?Segment $segment = null,
        public float $errorThresholdPercent = 5.0,
        public bool $killSwitchTriggered = false,
        public array $metadata = []
    ) {
        $this->percentage = max(0, min(100, $this->percentage));
    }

    /**
     * Determine active rollout percentage considering time-based stages.
     */
    public function getEffectivePercentage(?float $now = null): int
    {
        if ($this->killSwitchTriggered) {
            return 0;
        }

        $now = $now ?? microtime(true);
        $effective = $this->percentage;

        if (!empty($this->stages)) {
            foreach ($this->stages as $stage) {
                $stageTime = $stage['after_timestamp'] ?? $stage['time'] ?? 0;
                $stagePct = (int)($stage['percentage'] ?? 0);
                if ($now >= $stageTime && $stagePct > $effective) {
                    $effective = min(100, $stagePct);
                }
            }
        }

        return $effective;
    }

    /**
     * Evaluate whether given context receives the canary rollout.
     */
    public function evaluate(EvaluationContext $context, ?float $now = null): bool
    {
        if ($this->killSwitchTriggered) {
            return false;
        }

        // 1. Verify segment eligibility if configured
        if ($this->segment !== null && !$this->segment->matches($context)) {
            return false;
        }

        $effectivePercentage = $this->getEffectivePercentage($now);
        if ($effectivePercentage <= 0) {
            return false;
        }

        if ($effectivePercentage >= 100) {
            return true;
        }

        // 2. Consistent hashing bucket 0..99
        $hashInput = $this->flagKey . ':canary:' . $context->getIdentifier();
        $bucket = abs(crc32($hashInput)) % 100;

        return $bucket < $effectivePercentage;
    }

    /**
     * Check if error rate breaches safety threshold and automatically triggers kill-switch.
     */
    public function recordMetrics(int $totalRequests, int $errorCount): bool
    {
        if ($totalRequests <= 0) {
            return false;
        }

        $rate = ($errorCount / $totalRequests) * 100.0;
        if ($rate >= $this->errorThresholdPercent) {
            $this->triggerKillSwitch();
            return true;
        }

        return false;
    }

    /**
     * Manually or automatically engage emergency kill switch.
     */
    public function triggerKillSwitch(): void
    {
        $this->killSwitchTriggered = true;
    }

    /**
     * Reset emergency kill switch.
     */
    public function resetKillSwitch(): void
    {
        $this->killSwitchTriggered = false;
    }

    /**
     * Reconstruct from array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $segment = isset($data['segment']) && is_array($data['segment'])
            ? Segment::fromArray($data['segment'])
            : null;

        return new self(
            flagKey: (string)($data['flag_key'] ?? $data['flagKey'] ?? 'canary_flag'),
            percentage: (int)($data['percentage'] ?? 0),
            stages: (array)($data['stages'] ?? []),
            segment: $segment,
            errorThresholdPercent: (float)($data['error_threshold_percent'] ?? 5.0),
            killSwitchTriggered: (bool)($data['kill_switch_triggered'] ?? false),
            metadata: (array)($data['metadata'] ?? [])
        );
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'flag_key' => $this->flagKey,
            'percentage' => $this->percentage,
            'effective_percentage' => $this->getEffectivePercentage(),
            'stages' => $this->stages,
            'segment' => $this->segment?->toArray(),
            'error_threshold_percent' => $this->errorThresholdPercent,
            'kill_switch_triggered' => $this->killSwitchTriggered,
            'metadata' => $this->metadata,
        ];
    }
}
