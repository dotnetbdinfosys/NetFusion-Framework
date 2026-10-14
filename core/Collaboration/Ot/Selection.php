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

class Selection implements JsonSerializable
{
    public function __construct(
        public int $anchor,
        public int $head
    ) {
    }

    public static function create(int $anchor, ?int $head = null): self
    {
        return new self($anchor, $head ?? $anchor);
    }

    public function isCollapsed(): bool
    {
        return $this->anchor === $this->head;
    }

    public function getStart(): int
    {
        return min($this->anchor, $this->head);
    }

    public function getEnd(): int
    {
        return max($this->anchor, $this->head);
    }

    /**
     * Transform selection indices against an applied TextOperation.
     */
    public function transform(TextOperation $operation): self
    {
        $newAnchor = $this->transformIndex($this->anchor, $operation);
        $newHead = $this->transformIndex($this->head, $operation);

        return new self($newAnchor, $newHead);
    }

    protected function transformIndex(int $index, TextOperation $operation): int
    {
        $newIndex = $index;
        $ops = $operation->getOps();

        foreach ($ops as $op) {
            if (is_int($op)) {
                if ($op > 0) {
                    // Retain
                    $index -= $op;
                } else {
                    // Delete
                    $delLen = -$op;
                    if ($index >= $delLen) {
                        $index -= $delLen;
                        $newIndex -= $delLen;
                    } else {
                        $newIndex -= $index;
                        $index = 0;
                    }
                }
            } elseif (is_string($op)) {
                // Insert
                $insLen = mb_strlen($op, 'UTF-8');
                $newIndex += $insLen;
            }

            if ($index < 0) {
                break;
            }
        }

        return max(0, $newIndex);
    }

    public function toArray(): array
    {
        return [
            'anchor' => $this->anchor,
            'head' => $this->head,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            anchor: (int)($data['anchor'] ?? 0),
            head: (int)($data['head'] ?? 0)
        );
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
