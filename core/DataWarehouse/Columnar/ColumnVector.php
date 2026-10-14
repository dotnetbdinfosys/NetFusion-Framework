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

namespace NetFusion\Core\DataWarehouse\Columnar;

use NetFusion\Core\DataWarehouse\Contracts\CompressionCodecInterface;

class ColumnVector
{
    /**
     * @var array<int, mixed> Uncompressed in-memory values
     */
    protected array $values = [];

    protected ZoneMap $zoneMap;
    protected ?BitmapIndex $bitmapIndex = null;
    protected ?CompressionCodecInterface $codec = null;
    protected mixed $compressedPayload = null;

    /**
     * @param string $name Column name
     * @param string $type Column datatype ('int', 'float', 'string', 'bool', 'datetime')
     * @param CompressionCodecInterface|null $codec
     */
    public function __construct(
        protected string $name,
        protected string $type = 'string',
        ?CompressionCodecInterface $codec = null
    ) {
        $this->zoneMap = new ZoneMap();
        $this->codec = $codec;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getZoneMap(): ZoneMap
    {
        return $this->zoneMap;
    }

    public function getBitmapIndex(): ?BitmapIndex
    {
        return $this->bitmapIndex;
    }

    public function push(mixed $value): void
    {
        $castValue = $this->castValue($value);
        $this->values[] = $castValue;
        $this->zoneMap->add($castValue);
        $this->compressedPayload = null; // Invalidate compressed cache
    }

    public function pushBatch(array $values): void
    {
        foreach ($values as $val) {
            $this->push($val);
        }
    }

    public function get(int $index): mixed
    {
        if ($this->compressedPayload !== null && empty($this->values) && $this->codec !== null) {
            $this->values = $this->codec->decompress($this->compressedPayload);
        }

        return $this->values[$index] ?? null;
    }

    /**
     * Get entire vector as flat array.
     *
     * @return array<int, mixed>
     */
    public function toArray(): array
    {
        if ($this->compressedPayload !== null && empty($this->values) && $this->codec !== null) {
            $this->values = $this->codec->decompress($this->compressedPayload);
        }

        return $this->values;
    }

    public function count(): int
    {
        return $this->zoneMap->getRowCount();
    }

    /**
     * Build Bitmap Index for fast filtering on low-cardinality values.
     */
    public function buildBitmapIndex(): void
    {
        $this->bitmapIndex = new BitmapIndex();
        $this->bitmapIndex->build($this->toArray());
    }

    /**
     * Compress vector in memory using configured codec.
     */
    public function compress(): void
    {
        if ($this->codec !== null && !empty($this->values)) {
            $this->compressedPayload = $this->codec->compress($this->values);
            $this->values = []; // Free raw memory buffer
        }
    }

    /**
     * Decompress in-memory vector.
     */
    public function decompress(): void
    {
        if ($this->compressedPayload !== null && $this->codec !== null) {
            $this->values = $this->codec->decompress($this->compressedPayload);
            $this->compressedPayload = null;
        }
    }

    public function isCompressed(): bool
    {
        return $this->compressedPayload !== null;
    }

    public function getCompressedPayload(): mixed
    {
        return $this->compressedPayload;
    }

    protected function castValue(mixed $val): mixed
    {
        if ($val === null) {
            return null;
        }

        return match ($this->type) {
            'int', 'integer' => (int)$val,
            'float', 'double', 'decimal' => (float)$val,
            'bool', 'boolean' => (bool)$val,
            'string' => (string)$val,
            default => $val,
        };
    }
}
