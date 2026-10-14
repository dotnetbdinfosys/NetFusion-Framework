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

class Exposure
{
    /**
     * @param string               $id
     * @param string               $experimentKey
     * @param string               $variantName
     * @param string               $identifier    User or Anonymous subject ID
     * @param array<string, mixed> $context       Attributes snapshot
     * @param float                $timestamp
     */
    public function __construct(
        public readonly string $id,
        public readonly string $experimentKey,
        public readonly string $variantName,
        public readonly string $identifier,
        public readonly array $context = [],
        public readonly float $timestamp = 0.0
    ) {}

    /**
     * Build Exposure instance.
     */
    public static function create(
        string $experimentKey,
        string $variantName,
        EvaluationContext $context,
        ?string $id = null
    ): self {
        return new self(
            id: $id ?? 'exp_' . bin2hex(random_bytes(8)),
            experimentKey: $experimentKey,
            variantName: $variantName,
            identifier: $context->getIdentifier(),
            context: $context->toArray(),
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
            context: (array)($data['context'] ?? []),
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
            'context' => $this->context,
            'timestamp' => $this->timestamp,
        ];
    }
}
