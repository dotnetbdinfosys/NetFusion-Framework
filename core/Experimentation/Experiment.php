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

namespace NetFusion\Core\Experimentation;

use NetFusion\Core\Experimentation\Segment\Segment;

class Experiment
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_RUNNING = 'running';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_CONCLUDED = 'concluded';

    /**
     * @param string               $key               Unique experiment slug (e.g. 'checkout_v2')
     * @param string               $name              Human-readable title
     * @param string               $status            STATUS_* constant
     * @param list<Variant>        $variants          Configured variants
     * @param Segment|null         $segment           Targeting segment constraint
     * @param list<string>         $goals             Conversion goals (e.g. ['checkout_complete', 'click_cta'])
     * @param int                  $trafficAllocation Percentage of eligible traffic to include (0 - 100)
     * @param string|null          $winnerVariant     Manually or statistically declared winner
     * @param string|null          $fallbackVariant   Fallback variant when not running or excluded from traffic
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $key,
        public string $name = '',
        public string $status = self::STATUS_RUNNING,
        public array $variants = [],
        public ?Segment $segment = null,
        public array $goals = [],
        public int $trafficAllocation = 100,
        public ?string $winnerVariant = null,
        public ?string $fallbackVariant = null,
        public array $metadata = []
    ) {
        $this->trafficAllocation = max(0, min(100, $this->trafficAllocation));
        if (empty($this->variants)) {
            $this->variants = [
                new Variant('control', 50),
                new Variant('treatment', 50),
            ];
        }
    }

    /**
     * Determine if experiment is currently active and accepting traffic.
     */
    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    /**
     * Assign a deterministic variant to the evaluation context.
     */
    public function assignVariant(EvaluationContext $context): ?Variant
    {
        // 1. If concluded with a declared winner, always return the winner variant
        if ($this->status === self::STATUS_CONCLUDED && $this->winnerVariant !== null) {
            return $this->getVariant($this->winnerVariant) ?? $this->getFallback();
        }

        // 2. If not running, return fallback
        if ($this->status !== self::STATUS_RUNNING) {
            return $this->getFallback();
        }

        // 3. Evaluate segment constraints
        if ($this->segment !== null && !$this->segment->matches($context)) {
            return $this->getFallback();
        }

        $identifier = $context->getIdentifier();

        // 4. Traffic allocation gating (0 - 100%)
        if ($this->trafficAllocation < 100) {
            $trafficHash = abs(crc32($this->key . ':traffic:' . $identifier)) % 100;
            if ($trafficHash >= $this->trafficAllocation) {
                return $this->getFallback();
            }
        }

        // 5. Variant selection based on weights
        $totalWeight = 0;
        foreach ($this->variants as $v) {
            $totalWeight += max(0, $v->weight);
        }

        if ($totalWeight <= 0) {
            return $this->variants[0] ?? null;
        }

        $variantHash = abs(crc32($this->key . ':variant:' . $identifier)) % $totalWeight;
        $cumulative = 0;

        foreach ($this->variants as $variant) {
            $cumulative += max(0, $variant->weight);
            if ($variantHash < $cumulative) {
                return $variant;
            }
        }

        return $this->variants[count($this->variants) - 1] ?? null;
    }

    /**
     * Find a variant by name.
     */
    public function getVariant(string $name): ?Variant
    {
        foreach ($this->variants as $variant) {
            if ($variant->name === $name) {
                return $variant;
            }
        }
        return null;
    }

    /**
     * Retrieve fallback variant if configured, or the control variant.
     */
    public function getFallback(): ?Variant
    {
        if ($this->fallbackVariant !== null) {
            $fallback = $this->getVariant($this->fallbackVariant);
            if ($fallback !== null) {
                return $fallback;
            }
        }

        return $this->variants[0] ?? null;
    }

    /**
     * Reconstruct from array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $variants = [];
        foreach ((array)($data['variants'] ?? []) as $v) {
            $variants[] = $v instanceof Variant ? $v : Variant::fromArray((array)$v);
        }

        $segment = isset($data['segment']) && is_array($data['segment'])
            ? Segment::fromArray($data['segment'])
            : null;

        return new self(
            key: (string)($data['key'] ?? 'exp_' . bin2hex(random_bytes(4))),
            name: (string)($data['name'] ?? ''),
            status: (string)($data['status'] ?? self::STATUS_RUNNING),
            variants: $variants,
            segment: $segment,
            goals: (array)($data['goals'] ?? []),
            trafficAllocation: (int)($data['traffic_allocation'] ?? $data['trafficAllocation'] ?? 100),
            winnerVariant: isset($data['winner_variant']) ? (string)$data['winner_variant'] : null,
            fallbackVariant: isset($data['fallback_variant']) ? (string)$data['fallback_variant'] : null,
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
            'key' => $this->key,
            'name' => $this->name,
            'status' => $this->status,
            'variants' => array_map(fn(Variant $v) => $v->toArray(), $this->variants),
            'segment' => $this->segment?->toArray(),
            'goals' => $this->goals,
            'traffic_allocation' => $this->trafficAllocation,
            'winner_variant' => $this->winnerVariant,
            'fallback_variant' => $this->fallbackVariant,
            'metadata' => $this->metadata,
        ];
    }
}
