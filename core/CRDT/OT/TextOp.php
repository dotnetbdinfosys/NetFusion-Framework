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

namespace NetFusion\Core\CRDT\OT;

use NetFusion\Core\CRDT\Enums\OperationType;

/**
 * Atomic text operational transformation unit.
 */
class TextOp
{
    private OperationType $type;
    private int $position;
    private string $text;
    private int $length;
    private array $attributes;

    public function __construct(
        OperationType $type,
        int $position,
        string $text = '',
        int $length = 0,
        array $attributes = []
    ) {
        $this->type       = $type;
        $this->position   = max(0, $position);
        $this->text       = $text;
        $this->length     = $length > 0 ? $length : mb_strlen($text);
        $this->attributes = $attributes;
    }

    public static function insert(int $position, string $text, array $attributes = []): self
    {
        return new self(OperationType::INSERT, $position, $text, mb_strlen($text), $attributes);
    }

    public static function delete(int $position, int $length): self
    {
        return new self(OperationType::DELETE, $position, '', $length);
    }

    public static function retain(int $position, int $length, array $attributes = []): self
    {
        return new self(OperationType::RETAIN, $position, '', $length, $attributes);
    }

    public function getType(): OperationType
    {
        return $this->type;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getLength(): int
    {
        return $this->length;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function applyTo(string $document): string
    {
        $docLen = mb_strlen($document);
        $pos = min($this->position, $docLen);

        return match ($this->type) {
            OperationType::INSERT => mb_substr($document, 0, $pos) . $this->text . mb_substr($document, $pos),
            OperationType::DELETE => mb_substr($document, 0, $pos) . mb_substr($document, min($docLen, $pos + $this->length)),
            OperationType::RETAIN, OperationType::UPDATE, OperationType::MERGE => $document,
        };
    }

    public function toArray(): array
    {
        return [
            'type'       => $this->type->value,
            'position'   => $this->position,
            'text'       => $this->text,
            'length'     => $this->length,
            'attributes' => $this->attributes,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            OperationType::from($data['type']),
            (int)($data['position'] ?? 0),
            (string)($data['text'] ?? ''),
            (int)($data['length'] ?? 0),
            (array)($data['attributes'] ?? [])
        );
    }
}
