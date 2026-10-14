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

class ColumnChunk
{
    /**
     * @var array<string, ColumnVector> Column name => ColumnVector
     */
    protected array $vectors = [];

    protected int $chunkId;
    protected int $maxRows;
    protected int $rowCount = 0;

    /**
     * @param int $chunkId
     * @param array<string, array{type: string, codec?: string}> $schema
     * @param int $maxRows
     */
    public function __construct(
        int $chunkId,
        protected array $schema = [],
        int $maxRows = 10000
    ) {
        $this->chunkId = $chunkId;
        $this->maxRows = $maxRows;

        $this->initVectors();
    }

    protected function initVectors(): void
    {
        foreach ($this->schema as $colName => $def) {
            $type = $def['type'] ?? 'string';
            $codecName = $def['codec'] ?? null;
            $codec = match ($codecName) {
                'rle' => new RunLengthCodec(),
                'dictionary' => new DictionaryCodec(),
                'bit_packing' => new BitPackingCodec(),
                default => null,
            };

            $this->vectors[$colName] = new ColumnVector($colName, $type, $codec);
        }
    }

    public function getChunkId(): int
    {
        return $this->chunkId;
    }

    public function isFull(): bool
    {
        return $this->rowCount >= $this->maxRows;
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }

    public function appendRow(array $row): void
    {
        foreach ($this->vectors as $colName => $vector) {
            $vector->push($row[$colName] ?? null);
        }
        $this->rowCount++;
    }

    public function getVector(string $colName): ?ColumnVector
    {
        return $this->vectors[$colName] ?? null;
    }

    /**
     * @return array<string, ColumnVector>
     */
    public function getVectors(): array
    {
        return $this->vectors;
    }

    /**
     * Check if entire chunk can be skipped using column ZoneMaps against filters.
     *
     * @param array<string, array{operator: string, value: mixed}> $filters
     * @return bool True if chunk might contain matches, False if safe to skip completely
     */
    public function canMatchFilters(array $filters): bool
    {
        if ($this->rowCount === 0) {
            return false;
        }

        foreach ($filters as $colName => $filter) {
            if (!isset($this->vectors[$colName])) {
                continue;
            }

            $zoneMap = $this->vectors[$colName]->getZoneMap();
            $operator = $filter['operator'] ?? '=';
            $value = $filter['value'] ?? null;

            if (!$zoneMap->canMatch($operator, $value)) {
                return false; // Zone map pruning: skip entire chunk!
            }
        }

        return true;
    }

    public function compress(): void
    {
        foreach ($this->vectors as $vector) {
            $vector->compress();
        }
    }
}
