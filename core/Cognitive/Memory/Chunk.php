<?php
/**
 * NetFusion Framework - Enterprise Cognitive Architecture
 *
 * Declarative knowledge chunk with slot-value pairs and ACT-R base-level activation.
 *
 * @package    NetFusion\Core\Cognitive\Memory
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Cognitive\Memory;

class Chunk
{
    /**
     * @var float[] Timestamps of past accesses (in seconds)
     */
    private array $accessHistory = [];

    /**
     * @param string $id Unique chunk identifier
     * @param string $type Concept / schema type (e.g. 'flight_booking', 'user_preference')
     * @param array<string, mixed> $slots Slot-value attributes
     * @param float $creationTime Initial timestamp (defaults to microtime(true))
     * @param float $decayRate Decay parameter d (default 0.5)
     */
    public function __construct(
        private readonly string $id,
        private readonly string $type,
        private array $slots = [],
        ?float $creationTime = null,
        private readonly float $decayRate = 0.5
    ) {
        $now = $creationTime ?? microtime(true);
        $this->accessHistory[] = $now;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSlots(): array
    {
        return $this->slots;
    }

    public function getSlot(string $key, mixed $default = null): mixed
    {
        return $this->slots[$key] ?? $default;
    }

    public function setSlot(string $key, mixed $value): void
    {
        $this->slots[$key] = $value;
    }

    public function hasSlot(string $key): bool
    {
        return array_key_exists($key, $this->slots);
    }

    /**
     * Record a new memory reference / rehearsal access.
     */
    public function touch(?float $timestamp = null): void
    {
        $this->accessHistory[] = $timestamp ?? microtime(true);
    }

    public function getAccessCount(): int
    {
        return count($this->accessHistory);
    }

    /**
     * Calculate ACT-R Base-Level Activation A_i at current time t:
     * A_i = ln( sum_{k=1}^n (t - t_k)^(-d) )
     *
     * @param float|null $currentTime Current evaluation time (defaults to microtime(true))
     */
    public function getBaseLevelActivation(?float $currentTime = null): float
    {
        $now = $currentTime ?? microtime(true);
        $sum = 0.0;
        $d = $this->decayRate;

        foreach ($this->accessHistory as $tAccess) {
            $delta = max(0.001, $now - $tAccess); // Avoid zero division
            $sum += pow($delta, -$d);
        }

        if ($sum <= 0.0) {
            return -999.0;
        }

        return log($sum);
    }

    /**
     * Compute slot matching score against a query pattern.
     * Returns 1.0 if all specified query slots match, -1.0 on mismatch.
     *
     * @param array<string, mixed> $query
     */
    public function matchScore(array $query): float
    {
        if (empty($query)) {
            return 1.0;
        }

        foreach ($query as $k => $v) {
            if (!array_key_exists($k, $this->slots)) {
                return -1.0;
            }
            if ($this->slots[$k] != $v) {
                return -1.0;
            }
        }

        return 1.0;
    }

    /**
     * @return array{id: string, type: string, slots: array<string, mixed>, access_count: int, activation: float}
     */
    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'type'         => $this->type,
            'slots'        => $this->slots,
            'access_count' => $this->getAccessCount(),
            'activation'   => round($this->getBaseLevelActivation(), 4),
        ];
    }
}
