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

namespace NetFusion\Core\Vector;

use NetFusion\Core\Vector\Contracts\VectorInterface;
use NetFusion\Core\Vector\Math\Vector;
use JsonSerializable;
use ArrayAccess;

class VectorDocument implements JsonSerializable, ArrayAccess
{
    /**
     * @param string $id Unique identifier
     * @param string $content Raw textual content
     * @param VectorInterface|null $embedding Dense vector embedding
     * @param array<string, mixed> $metadata Document metadata
     * @param string|null $parentId Optional parent document ID if chunked
     * @param int|null $chunkIndex Chunk order index
     * @param int|null $createdAt Timestamp
     */
    public function __construct(
        protected string $id,
        protected string $content,
        protected ?VectorInterface $embedding = null,
        protected array $metadata = [],
        protected ?string $parentId = null,
        protected ?int $chunkIndex = null,
        protected ?int $createdAt = null
    ) {
        $this->createdAt = $this->createdAt ?? time();
        // Ensure content is accessible in metadata
        $this->metadata['text'] = $this->content;
        $this->metadata['id'] = $this->id;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getEmbedding(): ?VectorInterface
    {
        return $this->embedding;
    }

    public function setEmbedding(VectorInterface $embedding): void
    {
        $this->embedding = $embedding;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
        $this->metadata['text'] = $this->content;
        $this->metadata['id'] = $this->id;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function getChunkIndex(): ?int
    {
        return $this->chunkIndex;
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt ?? time();
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->metadata[$offset]) || $offset === 'content' || $offset === 'id';
    }

    public function offsetGet(mixed $offset): mixed
    {
        if ($offset === 'id') return $this->id;
        if ($offset === 'content') return $this->content;
        return $this->metadata[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->set((string)$offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->metadata[$offset]);
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'content' => $this->content,
            'embedding' => $this->embedding?->toArray(),
            'metadata' => $this->metadata,
            'parentId' => $this->parentId,
            'chunkIndex' => $this->chunkIndex,
            'createdAt' => $this->createdAt,
        ];
    }

    public static function fromArray(array $data): self
    {
        $embedding = isset($data['embedding']) && is_array($data['embedding'])
            ? new Vector($data['embedding'])
            : null;

        return new self(
            (string)($data['id'] ?? uniqid('doc_')),
            (string)($data['content'] ?? ''),
            $embedding,
            (array)($data['metadata'] ?? []),
            isset($data['parentId']) ? (string)$data['parentId'] : null,
            isset($data['chunkIndex']) ? (int)$data['chunkIndex'] : null,
            isset($data['createdAt']) ? (int)$data['createdAt'] : null
        );
    }
}
