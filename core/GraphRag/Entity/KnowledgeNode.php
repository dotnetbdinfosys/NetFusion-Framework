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
use NetFusion\Core\GraphRag\Enums\ModalityType;

/**
 * Represents a Multi-Modal Knowledge Node (Chunk, Entity, or Concept) with dense vector embeddings.
 */
class KnowledgeNode implements JsonSerializable
{
    /**
     * @param string $id
     * @param string $label
     * @param string $content
     * @param ModalityType $modality
     * @param array<int, float> $vector
     * @param array<string, mixed> $properties
     * @param float $confidence
     */
    public function __construct(
        protected string $id,
        protected string $label,
        protected string $content = '',
        protected ModalityType $modality = ModalityType::TEXT,
        protected array $vector = [],
        protected array $properties = [],
        protected float $confidence = 1.0
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getModality(): ModalityType
    {
        return $this->modality;
    }

    /**
     * @return array<int, float>
     */
    public function getVector(): array
    {
        return $this->vector;
    }

    public function hasVector(): bool
    {
        return !empty($this->vector);
    }

    public function withVector(array $vector): self
    {
        $clone = clone $this;
        $clone->vector = array_values(array_map('floatval', $vector));
        return $clone;
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

    public function withProperty(string $key, mixed $value): self
    {
        $clone = clone $this;
        $clone->properties[$key] = $value;
        return $clone;
    }

    public function getConfidence(): float
    {
        return $this->confidence;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'label'      => $this->label,
            'content'    => $this->content,
            'modality'   => $this->modality->value,
            'vector'     => $this->vector,
            'properties' => $this->properties,
            'confidence' => $this->confidence,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
