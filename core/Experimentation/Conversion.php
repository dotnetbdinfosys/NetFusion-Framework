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

class Conversion
{
    /**
     * @param string               $id
     * @param string               $experimentKey
     * @param string               $variantName
     * @param string               $identifier
     * @param string               $goal          Goal name (e.g. 'checkout_completed', 'signup_click')
     * @param float                $value         Conversion monetary value or numeric weight (default: 1.0)
     * @param array<string, mixed> $metadata
     * @param float                $timestamp
     */
    public function __construct(
        public readonly string $id,
        public readonly string $experimentKey,
        public readonly string $variantName,
        public readonly string $identifier,
        public readonly string $goal,
        public readonly float $value = 1.0,
        public readonly array $metadata = [],
        public readonly float $timestamp = 0.0
    ) {}

    /**
     * Build Conversion instance.
     */
    public static function create(
        string $experimentKey,
        string $variantName,
        string $identifier,
        string $goal,
        float $value = 1.0,
        array $metadata = [],
        ?string $id = null
    ): self {
        return new self(
            id: $id ?? 'cnv_' . bin2hex(random_bytes(8)),
            experimentKey: $experimentKey,
            variantName: $variantName,
            identifier: $identifier,
            goal: $goal,
            value: $value,
            metadata: $metadata,
            timestamp: microtime(true)
        );
    }

    /**
     * Reconstruct from array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string)($data['id'] ?? bin2hex(random_bytes(8))),
            experimentKey: (string)($data['experiment_key'] ?? $data['experimentKey'] ?? ''),
            variantName: (string)($data['variant_name'] ?? $data['variantName'] ?? ''),
            identifier: (string)($data['identifier'] ?? ''),
            goal: (string)($data['goal'] ?? 'default_goal'),
            value: (float)($data['value'] ?? 1.0),
            metadata: (array)($data['metadata'] ?? []),
            timestamp: (float)($data['timestamp'] ?? microtime(true))
        );
    }

    /**
     * Export to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'experiment_key' => $this->experimentKey,
            'variant_name' => $this->variantName,
            'identifier' => $this->identifier,
            'goal' => $this->goal,
            'value' => $this->value,
            'metadata' => $this->metadata,
            'timestamp' => $this->timestamp,
        ];
    }
}
