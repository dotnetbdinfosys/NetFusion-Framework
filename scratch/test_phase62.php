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

require_once __DIR__ . '/../vendor/autoload.php';

use NetFusion\Core\Application;
use NetFusion\Core\DataWarehouse\Columnar\RunLengthCodec;
use NetFusion\Core\DataWarehouse\Columnar\DictionaryCodec;
use NetFusion\Core\DataWarehouse\Columnar\BitPackingCodec;
use NetFusion\Core\DataWarehouse\Columnar\ZoneMap;
use NetFusion\Core\DataWarehouse\Columnar\BitmapIndex;
use NetFusion\Core\DataWarehouse\Columnar\ColumnVector;
use NetFusion\Core\DataWarehouse\Columnar\ColumnarTable;
use NetFusion\Core\DataWarehouse\Schema\Dimension;
use NetFusion\Core\DataWarehouse\Schema\FactTable;
use NetFusion\Core\DataWarehouse\Schema\StarSchema;
use NetFusion\Core\DataWarehouse\Cube\OLAPCube;
use NetFusion\Core\DataWarehouse\BI\CalculatedMeasure;
use NetFusion\Core\DataWarehouse\BI\PivotTable;
use NetFusion\Core\DataWarehouse\BI\PivotRenderer;
use NetFusion\Core\DataWarehouse\DataWarehouseManager;

$testsPassed = 0;
$testsFailed = 0;

function assertTest(string $description, bool $condition): void
{
    global $testsPassed, $testsFailed;
    if ($condition) {
        echo "  [PASS] {$description}\n";
        $testsPassed++;
    } else {
        echo "  [FAIL] {$description}\n";
        $testsFailed++;
    }
}

echo "========================================================================\n";
echo "  NetFusion Framework — Phase 62 Test Suite\n";
echo "  Enterprise Data Warehouse, OLAP Columnar Storage & Dynamic BI Pivot\n";
echo "========================================================================\n\n";

// -----------------------------------------------------------------------------
// 1. Columnar Compression Codecs (RLE, Dictionary, Bit-Packing)
// -----------------------------------------------------------------------------
echo "1. Testing Columnar Compression Codecs...\n";

// RLE
$rle = new RunLengthCodec();
$rawRle = ['ACTIVE', 'ACTIVE', 'ACTIVE', 'PENDING', 'PENDING', 'CLOSED', 'CLOSED', 'CLOSED', 'CLOSED'];
$compressedRle = $rle->compress($rawRle);
$decompressedRle = $rle->decompress($compressedRle);
assertTest("RLE compresses repeated values into run counts", count($compressedRle) === 3);
assertTest("RLE decompresses back to original sequence accurately", $decompressedRle === $rawRle);

// Dictionary Codec
$dictCodec = new DictionaryCodec();
$rawDict = ['Dhaka', 'Chittagong', 'Dhaka', 'Sylhet', 'Dhaka', 'Chittagong'];
$compressedDict = $dictCodec->compress($rawDict);
$decompressedDict = $dictCodec->decompress($compressedDict);
assertTest("DictionaryCodec creates unique dictionary lookup", count($compressedDict['dictionary']) === 3);
assertTest("DictionaryCodec decompresses back to original values", $decompressedDict === $rawDict);

// BitPacking / Delta Codec
$bitCodec = new BitPackingCodec();
$rawInts = [1000, 1005, 1002, 1008, 1000];
$compressedBits = $bitCodec->compress($rawInts);
$decompressedBits = $bitCodec->decompress($compressedBits);
assertTest("BitPackingCodec captures minimum offset and deltas", $compressedBits['min'] === 1000 && count($compressedBits['data']) === 5);
assertTest("BitPackingCodec reconstructs exact integer sequence", $decompressedBits === $rawInts);

// -----------------------------------------------------------------------------
// 2. Zone Maps & Bitmap Indexing
// -----------------------------------------------------------------------------
echo "\n2. Testing Zone Maps & Bitmap Indexing...\n";

$zoneMap = new ZoneMap();
$zoneMap->addBatch([10, 25, 5, 80, 42]);
assertTest("ZoneMap tracks min and max correctly", $zoneMap->getMin() === 5 && $zoneMap->getMax() === 80);
assertTest("ZoneMap canMatch('=') identifies potential match", $zoneMap->canMatch('=', 42));
assertTest("ZoneMap canMatch('=') safely prunes out-of-range value (skips block)", !$zoneMap->canMatch('=', 150));
assertTest("ZoneMap canMatch('BETWEEN') recognizes range overlap", $zoneMap->canMatch('BETWEEN', [15, 30]));
assertTest("ZoneMap canMatch('>') recognizes boundary condition", $zoneMap->canMatch('>', 50) && !$zoneMap->canMatch('>', 90));

$bitmap = new BitmapIndex();
$bitmap->build(['US', 'EU', 'US', 'APAC', 'EU', 'US']);
$usMatches = $bitmap->queryEquals('US');
$euMatches = $bitmap->queryEquals('EU');
assertTest("BitmapIndex indexes unique values cardinality", $bitmap->getCardinality() === 3);
assertTest("BitmapIndex queryEquals finds exact row indices", $usMatches === [0, 2, 5] && $euMatches === [1, 4]);
assertTest("BitmapIndex static union blends match sets", BitmapIndex::union($usMatches, $euMatches) === [0, 2, 5, 1, 4]);

// -----------------------------------------------------------------------------
// 3. ColumnVector & ColumnarTable Query Engine
// -----------------------------------------------------------------------------
echo "\n3. Testing ColumnVector & Vectorized Columnar Table...\n";

$vec = new ColumnVector('revenue', 'float', new BitPackingCodec());
$vec->pushBatch([100.5, 200.0, 150.75, 300.25]);
assertTest("ColumnVector holds typed values and updates ZoneMap", $vec->count() === 4 && $vec->getZoneMap()->getMax() === 300.25);
$vec->compress();
assertTest("ColumnVector compresses payload in-memory", $vec->isCompressed());
assertTest("ColumnVector transparently reads value upon get()", $vec->get(1) === 200.0);

$colTable = new ColumnarTable('sales_facts', [
    'region' => ['type' => 'string', 'codec' => 'dictionary'],
    'category' => ['type' => 'string', 'codec' => 'dictionary'],
    'amount' => ['type' => 'float', 'codec' => 'bit_packing'],
    'quantity' => ['type' => 'int', 'codec' => 'bit_packing'],
], chunkCapacity: 5);

// Insert 10 rows across 2 chunks
$sampleData = [
    ['region' => 'North', 'category' => 'Electronics', 'amount' => 500.0, 'quantity' => 2],
    ['region' => 'North', 'category' => 'Furniture', 'amount' => 300.0, 'quantity' => 1],
    ['region' => 'South', 'category' => 'Electronics', 'amount' => 800.0, 'quantity' => 4],
    ['region' => 'South', 'category' => 'Clothing', 'amount' => 150.0, 'quantity' => 3],
    ['region' => 'North', 'category' => 'Clothing', 'amount' => 200.0, 'quantity' => 2],
    ['region' => 'West', 'category' => 'Electronics', 'amount' => 1200.0, 'quantity' => 5],
    ['region' => 'West', 'category' => 'Furniture', 'amount' => 450.0, 'quantity' => 2],
    ['region' => 'North', 'category' => 'Electronics', 'amount' => 600.0, 'quantity' => 3],
    ['region' => 'South', 'category' => 'Furniture', 'amount' => 700.0, 'quantity' => 2],
    ['region' => 'West', 'category' => 'Clothing', 'amount' => 250.0, 'quantity' => 1],
];
$colTable->insertBatch($sampleData);

assertTest("ColumnarTable allocates chunks based on capacity", count($colTable->getChunks()) === 2);
assertTest("ColumnarTable counts total rows accurately", $colTable->count() === 10);

// Vectorized query: Group By Region, SUM(amount), AVG(quantity)
$qRes = $colTable->query(
    filters: ['category' => 'Electronics'],
    groupBy: ['region'],
    aggregations: ['amount' => 'SUM', 'quantity' => 'AVG']
);

assertTest("Vectorized query executes filter and group by aggregations", count($qRes) === 3);
// Check North Electronics: 500 + 600 = 1100, avg qty = (2+3)/2 = 2.5
$northElec = array_values(array_filter($qRes, fn($r) => ($r['region'] ?? '') === 'North'))[0] ?? null;
assertTest("Vectorized query computes exact SUM and AVG aggregates", $northElec !== null && ($northElec['amount_sum'] ?? 0) === 1100.0 && ($northElec['quantity_avg'] ?? 0) === 2.5);


// -----------------------------------------------------------------------------
// 4. Star Schema, Dimension & Slowly Changing Dimensions (SCD Type 1 & 2)
// -----------------------------------------------------------------------------
echo "\n4. Testing Star Schema & Slowly Changing Dimensions (SCD)...\n";

$dimCustomer = new Dimension(
    name: 'dim_customer',
    naturalKey: 'customer_code',
    primaryKey: 'customer_sk',
    scdType: 2, // SCD Type 2 Historical Versioning
    hierarchy: ['country', 'city', 'customer_name']
);

$sk1 = $dimCustomer->upsert([
    'customer_code' => 'CUST-001',
    'customer_name' => 'John Doe',
    'country' => 'USA',
    'city' => 'New York',
]);
assertTest("Dimension creates initial surrogate key for member", $sk1 === 1);

// Update customer city -> Should spawn version 2 under SCD Type 2
$sk2 = $dimCustomer->upsert([
    'customer_code' => 'CUST-001',
    'customer_name' => 'John Doe',
    'country' => 'USA',
    'city' => 'San Francisco',
]);
assertTest("SCD Type 2 generates new surrogate key on tracked attribute change", $sk2 === 2);
$v1 = $dimCustomer->find($sk1);
$v2 = $dimCustomer->find($sk2);
assertTest("SCD Type 2 closes previous version with is_current=0", $v1['is_current'] === 0 && $v1['city'] === 'New York');
assertTest("SCD Type 2 marks new version with is_current=1 and incremented version", $v2['is_current'] === 1 && $v2['version'] === 2 && $v2['city'] === 'San Francisco');

// SCD Type 1 Dimension (In-Place Overwrite)
$dimProduct = new Dimension(
    name: 'dim_product',
    naturalKey: 'sku',
    primaryKey: 'product_sk',
    scdType: 1,
    hierarchy: ['category', 'sub_category', 'product_name']
);
$pSk1 = $dimProduct->upsert(['sku' => 'PROD-100', 'product_name' => 'Gaming Mouse', 'category' => 'Electronics', 'sub_category' => 'Accessories']);
$pSk2 = $dimProduct->upsert(['sku' => 'PROD-100', 'product_name' => 'Gaming Mouse RGB', 'category' => 'Electronics', 'sub_category' => 'Peripherals']);
assertTest("SCD Type 1 retains same surrogate key on update", $pSk1 === $pSk2);
assertTest("SCD Type 1 overwrites attributes in-place", $dimProduct->find($pSk1)['product_name'] === 'Gaming Mouse RGB');

// Fact Table & Star Schema
$factSales = new FactTable(
    name: 'fact_orders',
    dimensionKeys: [
        'customer_sk' => 'dim_customer',
        'product_sk' => 'dim_product',
    ],
    measures: [
        'net_amount' => 'SUM',
        'quantity' => 'SUM',
        'discount' => 'AVG',
    ]
);

$factSales->recordBatch([
    ['customer_sk' => 2, 'product_sk' => 1, 'net_amount' => 150.0, 'quantity' => 2, 'discount' => 10.0],
    ['customer_sk' => 2, 'product_sk' => 1, 'net_amount' => 75.0, 'quantity' => 1, 'discount' => 5.0],
]);
assertTest("FactTable records transactional measures into columnar engine", $factSales->count() === 2);

$starSchema = new StarSchema('sales_mart');
$starSchema->addDimension($dimCustomer);
$starSchema->addDimension($dimProduct);
$starSchema->addFactTable($factSales);

$denorm = $starSchema->denormalizeFact('fact_orders', ['customer_sk' => 2, 'product_sk' => 1, 'net_amount' => 150.0]);
assertTest("StarSchema denormalizes fact with joined dimension attributes", isset($denorm['dim_customer.city']) && $denorm['dim_customer.city'] === 'San Francisco');

// -----------------------------------------------------------------------------
// 5. Multi-Dimensional OLAP Cube (Slice, Dice, Drill-Down, Roll-Up)
// -----------------------------------------------------------------------------
echo "\n5. Testing Multi-Dimensional OLAP Cube Operations...\n";

// Prepare richer dimensional cube
$cubeSchema = new StarSchema('analytics_mart');
$cubeDimDate = new Dimension('dim_date', 'date_key', 'date_sk', 1, ['year', 'quarter', 'month']);
$cubeDimDate->upsert(['date_key' => '2026-Q1', 'year' => '2026', 'quarter' => 'Q1', 'month' => 'Jan']);
$cubeDimDate->upsert(['date_key' => '2026-Q2', 'year' => '2026', 'quarter' => 'Q2', 'month' => 'Apr']);

$cubeDimGeo = new Dimension('dim_geo', 'geo_code', 'geo_sk', 1, ['region', 'country', 'city']);
$cubeDimGeo->upsert(['geo_code' => 'US-NY', 'region' => 'North America', 'country' => 'USA', 'city' => 'New York']);
$cubeDimGeo->upsert(['geo_code' => 'UK-LD', 'region' => 'Europe', 'country' => 'UK', 'city' => 'London']);

$cubeFact = new FactTable('fact_revenue', ['date_sk' => 'dim_date', 'geo_sk' => 'dim_geo'], ['revenue' => 'SUM', 'profit' => 'SUM']);
$cubeFact->recordBatch([
    ['date_sk' => 1, 'geo_sk' => 1, 'revenue' => 10000.0, 'profit' => 3000.0],
    ['date_sk' => 1, 'geo_sk' => 2, 'revenue' => 8000.0, 'profit' => 2000.0],
    ['date_sk' => 2, 'geo_sk' => 1, 'revenue' => 12000.0, 'profit' => 4000.0],
    ['date_sk' => 2, 'geo_sk' => 2, 'revenue' => 9000.0, 'profit' => 2500.0],
]);

$cubeSchema->addDimension($cubeDimDate)->addDimension($cubeDimGeo)->addFactTable($cubeFact);

$olapCube = new OLAPCube('revenue_cube', $cubeSchema, 'fact_revenue', ['revenue' => 'SUM', 'profit' => 'SUM'], [
    'dim_date' => ['year', 'quarter', 'month'],
    'dim_geo' => ['region', 'country', 'city'],
]);

assertTest("OLAPCube materializes denormalized fact cells", $olapCube->count() === 4);

// Slice operation (Filter along 1 dimension: USA)
$usSlice = $olapCube->slice('dim_geo.country', 'USA')->getSlice();
assertTest("OLAPCube slice() isolates sub-cube by dimension value", $usSlice->count() === 2);
assertTest("CubeSlice aggregate() calculates sum across slice", $usSlice->aggregate('revenue', 'SUM') === 22000.0);

// Dice operation (Multi-dimensional filtering)
$diceResult = $olapCube->dice(['dim_date.quarter' => 'Q1', 'dim_geo.region' => 'North America'])->getSlice();
assertTest("OLAPCube dice() filters across multiple dimensions", $diceResult->count() === 1 && $diceResult->aggregate('profit') === 3000.0);

// Hierarchy Navigation (Drill Down & Roll Up)
$nav = $olapCube->getNavigator();
assertTest("HierarchyNavigator drillDown resolves next granular level", $nav->getDrillDownLevel('dim_date', 'year') === 'quarter');
assertTest("HierarchyNavigator rollUp resolves parent summary level", $nav->getRollUpLevel('dim_date', 'month') === 'quarter');

// -----------------------------------------------------------------------------
// 6. Dynamic BI Pivot Table, Calculated Measures & Renderers
// -----------------------------------------------------------------------------
echo "\n6. Testing Dynamic BI Pivot Reporting & Exporters...\n";

$pivot = new PivotTable($olapCube, rowDimensions: ['dim_date.quarter'], colDimensions: ['dim_geo.country'], measures: ['revenue' => 'SUM', 'profit' => 'SUM']);

// Add Calculated Measure: Profit Margin % = (profit / revenue) * 100
$pivot->addCalculatedMeasure(new CalculatedMeasure('profit_margin', function ($metrics) {
    $rev = (float)($metrics['revenue'] ?? 0.0);
    $prof = (float)($metrics['profit'] ?? 0.0);
    return $rev > 0 ? round(($prof / $rev) * 100, 2) : 0.0;
}, 'percent'));

$report = $pivot->generate();
assertTest("PivotTable generates structured cross-tabulation report", isset($report['records'], $report['row_totals'], $report['grand_totals']));
assertTest("PivotTable computes exact Grand Total revenue", $report['grand_totals']['revenue'] === 39000.0);
assertTest("PivotTable applies Calculated Measure to cell and grand totals", $report['grand_totals']['profit_margin'] === round((11500 / 39000) * 100, 2));

$mdTable = PivotRenderer::toMarkdown($report);
assertTest("PivotRenderer exports formatted Markdown table", str_contains($mdTable, 'Grand Total') && str_contains($mdTable, 'Q1'));

$csvOutput = PivotRenderer::toCsv($report);
assertTest("PivotRenderer exports CSV data stream", str_contains($csvOutput, 'Row Dimension') && str_contains($csvOutput, 'USA'));

$htmlWidget = PivotRenderer::toHtml($report, 'Quarterly Regional Financials');
assertTest("PivotRenderer exports responsive HTML table widget", str_contains($htmlWidget, '<table') && str_contains($htmlWidget, 'Quarterly Regional Financials'));

// -----------------------------------------------------------------------------
// 7. DataWarehouseManager & Global Helpers
// -----------------------------------------------------------------------------
echo "\n7. Testing DataWarehouseManager & Framework Integration...\n";

$app = new Application(realpath(__DIR__ . '/..'));
$dwMgr = new DataWarehouseManager();
$app->instance('datawarehouse', $dwMgr);

$registeredCube = $dwMgr->createCube('test_cube', $cubeSchema, 'fact_revenue', ['revenue' => 'SUM']);
assertTest("DataWarehouseManager registers and retrieves OLAPCube", $dwMgr->getCube('test_cube') !== null);
assertTest("Global helper datawarehouse() resolves DataWarehouseManager instance", function_exists('datawarehouse') && datawarehouse() instanceof DataWarehouseManager);
assertTest("Global helper olap_cube() finds cube by name", function_exists('olap_cube') && olap_cube('test_cube') instanceof OLAPCube);
assertTest("Global helper bi_pivot() creates fluent PivotTable builder", function_exists('bi_pivot') && bi_pivot('test_cube', ['dim_date.quarter']) instanceof PivotTable);

// Console Command Execution
$kernel = new \NetFusion\Core\Console\Kernel($app);
ob_start();
$exitCode = $kernel->call('dw:status', []);
$output = ob_get_clean();
assertTest("CLI command 'dw:status' executes and displays warehouse metrics", $exitCode === 0 && str_contains($output, 'NetFusion Enterprise Data Warehouse'));

echo "\n========================================================================\n";
echo "  Phase 62 Test Suite Finished: {$testsPassed} Passed, {$testsFailed} Failed (Total: " . ($testsPassed + $testsFailed) . ")\n";
echo "========================================================================\n\n";

exit($testsFailed > 0 ? 1 : 0);
