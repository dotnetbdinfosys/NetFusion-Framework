<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\GraphRag\Entity;

use JsonSerializable;

/**
 * Represents a semantic relationship connecting two KnowledgeNodes with an association weight.
 */
class KnowledgeEdge implements JsonSerializable
{
    /**
     * @param string $sourceId
     * @param string $targetId
     * @param string $relationship
     * @param float $weight
     * @param array<string, mixed> $properties
     */
    public function __construct(
        protected string $sourceId,
        protected string $targetId,
        protected string $relationship = 'RELATED_TO',
        protected float $weight = 1.0,
        protected array $properties = []
    ) {
    }

    public function getSourceId(): string
    {
        return $this->sourceId;
    }

    public function getTargetId(): string
    {
        return $this->targetId;
    }

    public function getRelationship(): string
    {
        return $this->relationship;
    }

    public function getWeight(): float
    {
        return $this->weight;
    }

    /**
     * @return array<string, mixed>
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    public function getProperty(string $key, mixed $default = null): mixed
    {
        return $this->properties[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sourceId'     => $this->sourceId,
            'targetId'     => $this->targetId,
            'relationship' => $this->relationship,
            'weight'       => $this->weight,
            'properties'   => $this->properties,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
