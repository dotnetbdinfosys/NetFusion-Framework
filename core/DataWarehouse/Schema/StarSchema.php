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

namespace NetFusion\Core\DataWarehouse\Schema;

class StarSchema
{
    /**
     * @var array<string, Dimension>
     */
    protected array $dimensions = [];

    /**
     * @var array<string, FactTable>
     */
    protected array $factTables = [];

    /**
     * @param string $name Schema name (e.g. 'ecommerce_sales_mart')
     */
    public function __construct(
        protected string $name
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function addDimension(Dimension $dimension): static
    {
        $this->dimensions[$dimension->getName()] = $dimension;
        return $this;
    }

    public function getDimension(string $name): ?Dimension
    {
        return $this->dimensions[$name] ?? null;
    }

    /**
     * @return array<string, Dimension>
     */
    public function getDimensions(): array
    {
        return $this->dimensions;
    }

    public function addFactTable(FactTable $factTable): static
    {
        // Validate that all dimension foreign keys match existing dimensions
        foreach ($factTable->getDimensionKeys() as $fk => $dimName) {
            if (!isset($this->dimensions[$dimName])) {
                // Warning or deferred registration
            }
        }

        $this->factTables[$factTable->getName()] = $factTable;
        return $this;
    }

    public function getFactTable(string $name): ?FactTable
    {
        return $this->factTables[$name] ?? null;
    }

    /**
     * @return array<string, FactTable>
     */
    public function getFactTables(): array
    {
        return $this->factTables;
    }

    /**
     * Denormalize a fact row by joining all dimension attributes for OLAP cube ingestion.
     *
     * @param string $factTableName
     * @param array<string, mixed> $factRow
     * @return array<string, mixed>
     */
    public function denormalizeFact(string $factTableName, array $factRow): array
    {
        $factTable = $this->getFactTable($factTableName);
        if ($factTable === null) {
            return $factRow;
        }

        $denormalized = $factRow;

        foreach ($factTable->getDimensionKeys() as $fk => $dimName) {
            $fkVal = (int)($factRow[$fk] ?? 0);
            $dim = $this->getDimension($dimName);
            if ($dim !== null && $fkVal > 0) {
                $dimRow = $dim->find($fkVal);
                if ($dimRow !== null) {
                    foreach ($dimRow as $attrName => $attrVal) {
                        $denormalized[$dimName . '.' . $attrName] = $attrVal;
                    }
                }
            }
        }

        return $denormalized;
    }
}
