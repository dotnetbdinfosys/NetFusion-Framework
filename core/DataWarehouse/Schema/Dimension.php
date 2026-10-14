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

use NetFusion\Core\DataWarehouse\Contracts\DimensionInterface;

class Dimension implements DimensionInterface
{
    /**
     * @var array<int, array<string, mixed>> SurrogateKey => Row attributes
     */
    protected array $records = [];

    /**
     * @var array<string, int> NaturalKey => Current Active SurrogateKey
     */
    protected array $naturalKeyIndex = [];

    protected int $nextSurrogateKey = 1;

    /**
     * @param string $name Dimension name (e.g. 'dim_customer', 'dim_date', 'dim_product')
     * @param string $naturalKey Column name representing business key (e.g. 'customer_code')
     * @param string $primaryKey Surrogate key column name (e.g. 'customer_sk')
     * @param int $scdType SCD Type (1 for overwrite, 2 for historical versioning)
     * @param array<string> $hierarchy Hierarchy levels (e.g. ['category', 'sub_category', 'product_name'])
     * @param array<string, string> $schema Column types
     */
    public function __construct(
        protected string $name,
        protected string $naturalKey = 'code',
        protected string $primaryKey = 'id',
        protected int $scdType = 1,
        protected array $hierarchy = [],
        protected array $schema = []
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }

    public function getNaturalKey(): string
    {
        return $this->naturalKey;
    }

    public function getScdType(): int
    {
        return $this->scdType;
    }

    public function getHierarchy(): array
    {
        return $this->hierarchy;
    }

    /**
     * Upsert a dimension member respecting SCD Type.
     *
     * @param array<string, mixed> $attributes
     * @return int Active Surrogate Key
     */
    public function upsert(array $attributes): int
    {
        $natVal = (string)($attributes[$this->naturalKey] ?? '');
        if ($natVal === '') {
            throw new \InvalidArgumentException("Natural key [{$this->naturalKey}] is missing in dimension [{$this->name}].");
        }

        if (isset($this->naturalKeyIndex[$natVal])) {
            $existingSk = $this->naturalKeyIndex[$natVal];
            $existing = $this->records[$existingSk];

            if ($this->scdType === 2) {
                // Check if any tracked attribute actually changed
                $hasChanged = false;
                foreach ($attributes as $k => $v) {
                    if ($k !== $this->primaryKey && isset($existing[$k]) && $existing[$k] !== $v) {
                        $hasChanged = true;
                        break;
                    }
                }

                if ($hasChanged) {
                    $newSk = $this->nextSurrogateKey++;
                    $scdResult = SCDHandler::applyType2($existing, $attributes, $newSk);
                    $this->records[$existingSk] = $scdResult['closed_record'];
                    $this->records[$newSk] = $scdResult['new_record'];
                    $this->naturalKeyIndex[$natVal] = $newSk;
                    return $newSk;
                }

                return $existingSk;
            } else {
                // SCD Type 1 In-Place Overwrite
                $updated = SCDHandler::applyType1($existing, $attributes);
                $this->records[$existingSk] = $updated;
                return $existingSk;
            }
        }

        // New record insertion
        $sk = $this->nextSurrogateKey++;
        $record = array_merge($attributes, [
            $this->primaryKey => $sk,
            'version' => 1,
            'is_current' => 1,
            'valid_from' => date('Y-m-d H:i:s'),
            'valid_to' => '9999-12-31 23:59:59',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->records[$sk] = $record;
        $this->naturalKeyIndex[$natVal] = $sk;

        return $sk;
    }

    public function find(int $surrogateKey): ?array
    {
        return $this->records[$surrogateKey] ?? null;
    }

    public function findByNaturalKey(string $naturalKeyVal): ?array
    {
        if (isset($this->naturalKeyIndex[$naturalKeyVal])) {
            $sk = $this->naturalKeyIndex[$naturalKeyVal];
            return $this->records[$sk] ?? null;
        }
        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->records;
    }

    public function count(): int
    {
        return count($this->records);
    }
}
