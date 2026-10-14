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

namespace NetFusion\Core\Wasm\Vm;

/**
 * WebAssembly Linear Memory implementation with page allocation, bounds checking,
 * and multi-byte numeric / string accessors.
 */
class LinearMemory
{
    public const PAGE_SIZE = 65536; // 64 KiB per Wasm page

    private string $buffer;
    private int $pages;
    private ?int $maxPages;

    public function __construct(int $initialPages = 1, ?int $maxPages = null)
    {
        if ($initialPages < 0) {
            throw new \InvalidArgumentException("Initial memory pages must be non-negative.");
        }
        if ($maxPages !== null && $maxPages < $initialPages) {
            throw new \InvalidArgumentException("Maximum pages cannot be less than initial pages.");
        }

        $this->pages = $initialPages;
        $this->maxPages = $maxPages;
        $this->buffer = str_repeat("\x00", $initialPages * self::PAGE_SIZE);
    }

    public function getPageCount(): int
    {
        return $this->pages;
    }

    public function getByteLength(): int
    {
        return $this->pages * self::PAGE_SIZE;
    }

    /**
     * Grow memory by delta pages.
     * Returns previous page count on success, or -1 on failure.
     */
    public function grow(int $deltaPages): int
    {
        if ($deltaPages < 0) {
            return -1;
        }
        if ($deltaPages === 0) {
            return $this->pages;
        }

        $newPages = $this->pages + $deltaPages;
        if ($this->maxPages !== null && $newPages > $this->maxPages) {
            return -1;
        }

        // Standard Wasm limit is 65536 pages (4 GiB)
        if ($newPages > 65536) {
            return -1;
        }

        $oldPages = $this->pages;
        $this->buffer .= str_repeat("\x00", $deltaPages * self::PAGE_SIZE);
        $this->pages = $newPages;

        return $oldPages;
    }

    public function checkBounds(int $offset, int $length): void
    {
        if ($offset < 0 || $length < 0 || ($offset + $length) > strlen($this->buffer)) {
            throw TrapException::memoryOutOfBounds($offset, $length, strlen($this->buffer));
        }
    }

    public function readByte(int $offset): int
    {
        $this->checkBounds($offset, 1);
        return ord($this->buffer[$offset]);
    }

    public function writeByte(int $offset, int $byte): void
    {
        $this->checkBounds($offset, 1);
        $this->buffer[$offset] = chr($byte & 0xFF);
    }

    public function readBytes(int $offset, int $length): string
    {
        $this->checkBounds($offset, $length);
        return substr($this->buffer, $offset, $length);
    }

    public function writeBytes(int $offset, string $data): void
    {
        $len = strlen($data);
        $this->checkBounds($offset, $len);
        for ($i = 0; $i < $len; $i++) {
            $this->buffer[$offset + $i] = $data[$i];
        }
    }

    public function readI32(int $offset): int
    {
        $this->checkBounds($offset, 4);
        $bytes = substr($this->buffer, $offset, 4);
        $val = unpack('l', $bytes)[1]; // Signed 32-bit little-endian
        return (int) $val;
    }

    public function writeI32(int $offset, int $value): void
    {
        $this->checkBounds($offset, 4);
        $bytes = pack('l', $value);
        for ($i = 0; $i < 4; $i++) {
            $this->buffer[$offset + $i] = $bytes[$i];
        }
    }

    public function readI64(int $offset): int
    {
        $this->checkBounds($offset, 8);
        $bytes = substr($this->buffer, $offset, 8);
        $val = unpack('q', $bytes)[1]; // Signed 64-bit little-endian
        return (int) $val;
    }

    public function writeI64(int $offset, int $value): void
    {
        $this->checkBounds($offset, 8);
        $bytes = pack('q', $value);
        for ($i = 0; $i < 8; $i++) {
            $this->buffer[$offset + $i] = $bytes[$i];
        }
    }

    public function readNullTerminatedString(int $offset, int $maxLen = 4096): string
    {
        $str = '';
        $curr = $offset;
        $total = strlen($this->buffer);

        while ($curr < $total && (strlen($str) < $maxLen)) {
            $ch = $this->buffer[$curr++];
            if ($ch === "\x00") {
                break;
            }
            $str .= $ch;
        }

        return $str;
    }

    public function getBuffer(): string
    {
        return $this->buffer;
    }
}
