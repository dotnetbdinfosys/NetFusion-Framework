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

namespace NetFusion\Core\Collaboration\Document;

use JsonSerializable;

class DocumentSnapshot implements JsonSerializable
{
    public function __construct(
        public readonly string $id,
        public string $title,
        public string $content,
        public int $revision,
        public ?string $authorId = null,
        public array $metadata = [],
        public ?string $updatedAt = null
    ) {
        $this->updatedAt = $updatedAt ?? date('Y-m-d H:i:s');
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'revision' => $this->revision,
            'author_id' => $this->authorId,
            'metadata' => $this->metadata,
            'updated_at' => $this->updatedAt,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (string)$data['id'],
            title: (string)($data['title'] ?? 'Untitled Document'),
            content: (string)($data['content'] ?? ''),
            revision: (int)($data['revision'] ?? 0),
            authorId: isset($data['author_id']) ? (string)$data['author_id'] : null,
            metadata: (array)($data['metadata'] ?? []),
            updatedAt: (string)($data['updated_at'] ?? date('Y-m-d H:i:s'))
        );
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
