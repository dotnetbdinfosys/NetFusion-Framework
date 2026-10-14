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

namespace NetFusion\Core\Collaboration\Ot;

use JsonSerializable;

class Cursor implements JsonSerializable
{
    public function __construct(
        public readonly string $userId,
        public readonly string $username,
        public readonly string $color,
        public Selection $selection,
        public float $updatedAt = 0.0
    ) {
        $this->updatedAt = $updatedAt > 0.0 ? $updatedAt : microtime(true);
    }

    public function transform(TextOperation $op): self
    {
        $this->selection = $this->selection->transform($op);
        $this->updatedAt = microtime(true);
        return $this;
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'username' => $this->username,
            'color' => $this->color,
            'selection' => $this->selection->toArray(),
            'updated_at' => $this->updatedAt,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            userId: (string)($data['user_id'] ?? ''),
            username: (string)($data['username'] ?? 'Anonymous'),
            color: (string)($data['color'] ?? '#3b82f6'),
            selection: Selection::fromArray((array)($data['selection'] ?? [])),
            updatedAt: (float)($data['updated_at'] ?? microtime(true))
        );
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
