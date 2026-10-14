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

namespace NetFusion\Core\Search\Models;

class Document
{
    /**
     * @param string $id Unique document identifier
     * @param array<string, mixed> $fields Searchable and filterable text/data fields
     * @param array<float>|null $vector Dense semantic embedding vector (for hybrid search)
     * @param float $boost Custom static document boost score multiplier (default 1.0)
     * @param array<string> $tags Classification and indexing tags
     */
    public function __construct(
        protected string $id,
        protected array $fields = [],
        protected ?array $vector = null,
        protected float $boost = 1.0,
        protected array $tags = []
    ) {
    }

    public static function create(string $id, array $fields = [], ?array $vector = null, float $boost = 1.0): static
    {
        return new static($id, $fields, $vector, $boost);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->fields[$key] ?? $default;
    }

    public function set(string $key, mixed $value): static
    {
        $this->fields[$key] = $value;
        return $this;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->fields);
    }

    public function getVector(): ?array
    {
        return $this->vector;
    }

    public function setVector(?array $vector): static
    {
        $this->vector = $vector;
        return $this;
    }

    public function getBoost(): float
    {
        return $this->boost;
    }

    public function setBoost(float $boost): static
    {
        $this->boost = $boost;
        return $this;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function addTag(string $tag): static
    {
        if (!in_array($tag, $this->tags, true)) {
            $this->tags[] = $tag;
        }
        return $this;
    }

    /**
     * Concatenate all string fields into a single text representation for index scanning.
     */
    public function getCombinedText(array $excludedFields = []): string
    {
        $chunks = [];
        foreach ($this->fields as $key => $val) {
            if (in_array($key, $excludedFields, true)) {
                continue;
            }
            if (is_string($val)) {
                $chunks[] = $val;
            } elseif (is_array($val)) {
                $chunks[] = implode(' ', array_filter($val, 'is_string'));
            } elseif (is_numeric($val)) {
                $chunks[] = (string)$val;
            }
        }
        return implode(' ', $chunks);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'fields' => $this->fields,
            'vector' => $this->vector,
            'boost' => $this->boost,
            'tags' => $this->tags,
        ];
    }
}
