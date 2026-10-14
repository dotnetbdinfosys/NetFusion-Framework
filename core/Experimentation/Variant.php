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

class Variant
{
    /**
     * @param string               $name        Variant key (e.g. 'control', 'treatment_a', 'blue_button')
     * @param int                  $weight      Traffic weight (e.g. 50, 25, 25)
     * @param array<string, mixed> $payload     Arbitrary dynamic JSON/array payload (e.g. button colors, discount %, pricing tiers)
     * @param string|null          $description
     */
    public function __construct(
        public readonly string $name,
        public readonly int $weight = 50,
        public readonly array $payload = [],
        public readonly ?string $description = null
    ) {}

    /**
     * Reconstruct variant from array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string)($data['name'] ?? 'control'),
            weight: (int)($data['weight'] ?? 50),
            payload: (array)($data['payload'] ?? []),
            description: isset($data['description']) ? (string)$data['description'] : null
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
            'name' => $this->name,
            'weight' => $this->weight,
            'payload' => $this->payload,
            'description' => $this->description,
        ];
    }
}
