<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | Author    : Md. Atiquzzaman Ashish
 | Company   : DotNetBD
 | Website   : www.dotnetbd.com
 | Copyright : © DotNetBD. All rights reserved.
 | License   : MIT
 | Version   : 1.0.0
 |
 |--------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace NetFusion\Core\Wasm\Binary;

use NetFusion\Core\Wasm\Types\ValueType;

/**
 * Sequential stream reader for WebAssembly binary payloads.
 */
class BinaryReader
{
    private string $buffer;
    private int $offset;
    private int $length;

    public function __construct(string $buffer, int $offset = 0)
    {
        $this->buffer = $buffer;
        $this->offset = $offset;
        $this->length = strlen($buffer);
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function setOffset(int $offset): void
    {
        if ($offset < 0 || $offset > $this->length) {
            throw new \OutOfBoundsException("Offset {$offset} is out of bounds [0, {$this->length}].");
        }
        $this->offset = $offset;
    }

    public function isEof(): bool
    {
        return $this->offset >= $this->length;
    }

    public function remaining(): int
    {
        return max(0, $this->length - $this->offset);
    }

    public function readByte(): int
    {
        if ($this->offset >= $this->length) {
            throw new \UnderflowException("Unexpected EOF reading byte at offset {$this->offset}.");
        }
        return ord($this->buffer[$this->offset++]);
    }

    public function peekByte(): int
    {
        if ($this->offset >= $this->length) {
            throw new \UnderflowException("Unexpected EOF peeking byte at offset {$this->offset}.");
        }
        return ord($this->buffer[$this->offset]);
    }

    public function readBytes(int $len): string
    {
        if ($len < 0) {
            throw new \InvalidArgumentException("Length must be non-negative.");
        }
        if ($this->offset + $len > $this->length) {
            throw new \UnderflowException("Unexpected EOF reading {$len} bytes at offset {$this->offset} (available: {$this->remaining()}).");
        }
        $bytes = substr($this->buffer, $this->offset, $len);
        $this->offset += $len;
        return $bytes;
    }

    public function readUint32(): int
    {
        return Leb128::decodeUint($this->buffer, $this->offset, 32);
    }

    public function readInt32(): int
    {
        return Leb128::decodeInt($this->buffer, $this->offset, 32);
    }

    public function readUint64(): int
    {
        return Leb128::decodeUint($this->buffer, $this->offset, 64);
    }

    public function readInt64(): int
    {
        return Leb128::decodeInt($this->buffer, $this->offset, 64);
    }

    public function readFloat32(): float
    {
        $bytes = $this->readBytes(4);
        $val = unpack('g', $bytes)[1]; // 'g' is little-endian 32-bit float
        return (float) $val;
    }

    public function readFloat64(): float
    {
        $bytes = $this->readBytes(8);
        $val = unpack('e', $bytes)[1]; // 'e' is little-endian 64-bit float
        return (float) $val;
    }

    public function readString(): string
    {
        $len = $this->readUint32();
        return $this->readBytes($len);
    }

    public function readValueType(): ValueType
    {
        $byte = $this->readByte();
        return ValueType::fromByte($byte);
    }

    /**
     * Read a Wasm vector (32-bit count followed by elements).
     *
     * @template T
     * @param callable(BinaryReader): T $elementReader
     * @return array<T>
     */
    public function readVector(callable $elementReader): array
    {
        $count = $this->readUint32();
        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $items[] = $elementReader($this);
        }
        return $items;
    }

    /**
     * Create a sliced sub-reader for isolated section parsing.
     */
    public function slice(int $len): self
    {
        $data = $this->readBytes($len);
        return new self($data);
    }
}
