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

namespace NetFusion\Core\Graph\Models;

use NetFusion\Core\Graph\Contracts\EdgeInterface;

class Edge implements EdgeInterface
{
    /**
     * @param string $id
     * @param string $sourceId
     * @param string $targetId
     * @param string $type
     * @param bool $directed
     * @param float $weight
     * @param array<string, mixed> $properties
     */
    public function __construct(
        protected string $id,
        protected string $sourceId,
        protected string $targetId,
        protected string $type = 'RELATED_TO',
        protected bool $directed = true,
        protected float $weight = 1.0,
        protected array $properties = []
    ) {
    }

    public static function create(
        string $id,
        string $sourceId,
        string $targetId,
        string $type = 'RELATED_TO',
        bool $directed = true,
        float $weight = 1.0,
        array $properties = []
    ): self {
        return new self($id, $sourceId, $targetId, $type, $directed, $weight, $properties);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getSourceId(): string
    {
        return $this->sourceId;
    }

    public function getTargetId(): string
    {
        return $this->targetId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function isDirected(): bool
    {
        return $this->directed;
    }

    public function getWeight(): float
    {
        return $this->weight;
    }

    public function setWeight(float $weight): static
    {
        $this->weight = $weight;
        return $this;
    }

    public function getProperties(): array
    {
        return $this->properties;
    }

    public function getProperty(string $key, mixed $default = null): mixed
    {
        return $this->properties[$key] ?? $default;
    }

    public function setProperty(string $key, mixed $value): static
    {
        $this->properties[$key] = $value;
        return $this;
    }

    public function hasProperty(string $key): bool
    {
        return array_key_exists($key, $this->properties);
    }

    public function removeProperty(string $key): static
    {
        unset($this->properties[$key]);
        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'source_id' => $this->sourceId,
            'target_id' => $this->targetId,
            'type' => $this->type,
            'directed' => $this->directed,
            'weight' => $this->weight,
            'properties' => $this->properties,
        ];
    }
}
