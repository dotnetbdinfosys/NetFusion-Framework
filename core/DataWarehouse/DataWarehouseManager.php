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

namespace NetFusion\Core\DataWarehouse;

use NetFusion\Core\DataWarehouse\Schema\StarSchema;
use NetFusion\Core\DataWarehouse\Schema\Dimension;
use NetFusion\Core\DataWarehouse\Schema\FactTable;
use NetFusion\Core\DataWarehouse\Cube\OLAPCube;
use NetFusion\Core\DataWarehouse\BI\PivotTable;
use PDO;

class DataWarehouseManager
{
    /**
     * @var array<string, StarSchema>
     */
    protected array $schemas = [];

    /**
     * @var array<string, OLAPCube>
     */
    protected array $cubes = [];

    protected ?PDO $pdo = null;

    /**
     * @param array<string, mixed> $config
     * @param PDO|null $pdo
     */
    public function __construct(
        protected array $config = [],
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo;
    }

    public function schema(string $name): StarSchema
    {
        if (!isset($this->schemas[$name])) {
            $this->schemas[$name] = new StarSchema($name);
        }
        return $this->schemas[$name];
    }

    public function dimension(
        string $name,
        string $naturalKey = 'code',
        string $primaryKey = 'id',
        int $scdType = 1,
        array $hierarchy = [],
        array $schema = []
    ): Dimension {
        return new Dimension($name, $naturalKey, $primaryKey, $scdType, $hierarchy, $schema);
    }

    public function fact(
        string $name,
        array $dimensionKeys = [],
        array $measures = [],
        array $additionalColumns = []
    ): FactTable {
        return new FactTable($name, $dimensionKeys, $measures, $additionalColumns);
    }

    public function createCube(
        string $name,
        StarSchema $starSchema,
        string $factTableName,
        array $measures = [],
        array $hierarchies = []
    ): OLAPCube {
        $cube = new OLAPCube($name, $starSchema, $factTableName, $measures, $hierarchies);
        $this->cubes[$name] = $cube;
        return $cube;
    }

    public function registerCube(OLAPCube $cube): static
    {
        $this->cubes[$cube->getName()] = $cube;
        return $this;
    }

    public function getCube(string $name): ?OLAPCube
    {
        return $this->cubes[$name] ?? null;
    }

    /**
     * @return array<string, OLAPCube>
     */
    public function getCubes(): array
    {
        return $this->cubes;
    }

    public function pivot(
        string $cubeName,
        array $rows = [],
        array $columns = [],
        array $measures = []
    ): PivotTable {
        $cube = $this->getCube($cubeName);
        if ($cube === null) {
            throw new \InvalidArgumentException("OLAP Cube [{$cubeName}] not found.");
        }

        return new PivotTable($cube, $rows, $columns, $measures);
    }

    /**
     * Persist cube definition & metadata to database.
     */
    public function saveCubeMetadata(OLAPCube $cube): void
    {
        if ($this->pdo === null) {
            return;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO nf_dw_cubes (name, fact_table, measures_json, hierarchies_json, total_rows, updated_at)
            VALUES (:name, :fact, :measures, :hierarchies, :rows, :updated_at)
            ON CONFLICT(name) DO UPDATE SET
                measures_json = :measures_up,
                hierarchies_json = :hierarchies_up,
                total_rows = :rows_up,
                updated_at = :updated_at_up
        ");

        $now = date('Y-m-d H:i:s');
        $measuresJson = json_encode($cube->getMeasures());
        $hierarchiesJson = json_encode([]);

        $stmt->execute([
            ':name' => $cube->getName(),
            ':fact' => 'fact_table',
            ':measures' => $measuresJson,
            ':hierarchies' => $hierarchiesJson,
            ':rows' => $cube->count(),
            ':updated_at' => $now,
            ':measures_up' => $measuresJson,
            ':hierarchies_up' => $hierarchiesJson,
            ':rows_up' => $cube->count(),
            ':updated_at_up' => $now,
        ]);
    }
}
