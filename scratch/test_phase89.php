<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework - Phase 89 Geospatial Engine & H3 Indexing Test Suite
 * |--------------------------------------------------------------------------
 */

require_once __DIR__ . '/../vendor/autoload.php';

use NetFusion\Core\Database\Connection;
use NetFusion\Core\Spatial\Enums\GeoFenceEvent;
use NetFusion\Core\Spatial\Enums\GeometryType;
use NetFusion\Core\Spatial\Exceptions\InvalidCoordinateException;
use NetFusion\Core\Spatial\Geodesic\DistanceCalculator;
use NetFusion\Core\Spatial\GeoFence\GeoFence;
use NetFusion\Core\Spatial\GeoFence\GeoFenceMonitor;
use NetFusion\Core\Spatial\GeoFence\RayCaster;
use NetFusion\Core\Spatial\Geometry\BoundingBox;
use NetFusion\Core\Spatial\Geometry\GeoJson;
use NetFusion\Core\Spatial\Geometry\LineString;
use NetFusion\Core\Spatial\Geometry\Point;
use NetFusion\Core\Spatial\Geometry\Polygon;
use NetFusion\Core\Spatial\GeoSpatialManager;
use NetFusion\Core\Spatial\H3\H3Index;
use NetFusion\Core\Spatial\Index\RTree;

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $testName): void
{
    global $passed, $failed;
    if ($condition) {
        echo "[\033[32mPASS\033[0m] {$testName}\n";
        $passed++;
    } else {
        echo "[\033[31mFAIL\033[0m] {$testName}\n";
        $failed++;
    }
}

echo "========================================================\n";
echo " NetFusion Phase 89: Geospatial & H3 Engine Test Suite  \n";
echo "========================================================\n\n";

// -----------------------------------------------------------------------------
// 1. Spatial Geometry Primitives (Point, BoundingBox, LineString, Polygon)
// -----------------------------------------------------------------------------
$dhaka = new Point(23.8103, 90.4125, 12.5);
assertTest($dhaka->getLat() === 23.8103, "Point latitude accessor works");
assertTest($dhaka->getLng() === 90.4125, "Point longitude accessor works");
assertTest($dhaka->getAltitude() === 12.5, "Point altitude accessor works");
assertTest($dhaka->getType() === GeometryType::POINT, "Point geometry type is POINT");
assertTest($dhaka->toWkt() === "POINT(90.412500 23.810300 12.500000)", "Point WKT serialized in lng-lat-alt format");

// Coordinate bounds validation
$invalidCoordCaught = false;
try {
    new Point(95.0, 10.0);
} catch (InvalidCoordinateException) {
    $invalidCoordCaught = true;
}
assertTest($invalidCoordCaught, "Point throws InvalidCoordinateException on latitude > 90");

// BoundingBox
$bbox = new BoundingBox(23.7000, 90.3000, 23.9000, 90.5000);
assertTest($bbox->containsPoint($dhaka) === true, "BoundingBox contains internal Point");
assertTest($bbox->containsPoint(new Point(24.0, 90.4)) === false, "BoundingBox excludes external Point");

$intersectingBox = new BoundingBox(23.8500, 90.4500, 24.0000, 90.6000);
$disjointBox = new BoundingBox(25.0000, 91.0000, 26.0000, 92.0000);
assertTest($bbox->intersects($intersectingBox) === true, "BoundingBox intersects overlapping box");
assertTest($bbox->intersects($disjointBox) === false, "BoundingBox does not intersect disjoint box");

$expanded = $bbox->expand(new Point(24.0, 90.6));
assertTest($expanded->maxLat === 24.0 && $expanded->maxLng === 90.6, "BoundingBox expand enlarges boundaries");

// LineString
$path = new LineString([
    new Point(23.70, 90.35),
    new Point(23.75, 90.40),
    new Point(23.80, 90.45),
]);
assertTest($path->count() === 3, "LineString contains 3 vertices");
assertTest($path->getStartPoint()->getLat() === 23.70, "LineString start point matches");
assertTest($path->getEndPoint()->getLat() === 23.80, "LineString end point matches");
assertTest($path->getType() === GeometryType::LINE_STRING, "LineString type is LINE_STRING");

// Polygon (with exterior ring auto-closing and interior hole)
$exterior = [
    new Point(0.0, 0.0),
    new Point(0.0, 10.0),
    new Point(10.0, 10.0),
    new Point(10.0, 0.0),
]; // Missing closing point, should auto-close
$hole = [
    new Point(4.0, 4.0),
    new Point(4.0, 6.0),
    new Point(6.0, 6.0),
    new Point(6.0, 4.0),
];
$poly = new Polygon($exterior, [$hole]);
assertTest(count($poly->getExteriorRing()) === 5, "Polygon exterior ring auto-closed to 5 points");
assertTest($poly->hasHoles() === true, "Polygon detects interior hole");
assertTest($poly->getType() === GeometryType::POLYGON, "Polygon type is POLYGON");

// -----------------------------------------------------------------------------
// 2. GeoJSON Parser & Serializer (RFC 7946)
// -----------------------------------------------------------------------------
$geojsonStr = '{"type":"Point","coordinates":[90.4125,23.8103]}';
$parsedGeo = GeoJson::parse($geojsonStr);
assertTest($parsedGeo instanceof Point, "GeoJson parses Point GeoJSON string");
assertTest(abs($parsedGeo->getLat() - 23.8103) < 1e-4, "Parsed Point latitude matches");

$polyJson = '{"type":"Polygon","coordinates":[[[0,0],[10,0],[10,10],[0,10],[0,0]]]}';
$parsedPoly = GeoJson::parse($polyJson);
assertTest($parsedPoly instanceof Polygon, "GeoJson parses Polygon GeoJSON string");

$feature = GeoJson::feature($dhaka, ['city' => 'Dhaka', 'country' => 'BD'], id: 'feat-1');
assertTest($feature['type'] === 'Feature', "GeoJson feature creates valid Feature object");
assertTest($feature['properties']['city'] === 'Dhaka', "GeoJson feature preserves properties");

$fc = GeoJson::featureCollection([$feature], $bbox);
assertTest($fc['type'] === 'FeatureCollection' && count($fc['features']) === 1, "GeoJson creates FeatureCollection with bbox");

// -----------------------------------------------------------------------------
// 3. Geodesic Calculations (Haversine, Vincenty, Bearing, Destination)
// -----------------------------------------------------------------------------
// London (51.5074, -0.1278) to Paris (48.8566, 2.3522)
$london = new Point(51.5074, -0.1278);
$paris = new Point(48.8566, 2.3522);

$haversineDist = DistanceCalculator::haversine($london, $paris);
assertTest($haversineDist > 340000 && $haversineDist < 345000, "Haversine distance London to Paris is ~343 km");

$vincentyDist = DistanceCalculator::vincenty($london, $paris);
assertTest($vincentyDist > 340000 && $vincentyDist < 345000, "Vincenty WGS84 distance London to Paris is ~343 km");
assertTest(abs($haversineDist - $vincentyDist) < 2000, "Haversine and Vincenty agree within 0.5%");

$bearing = DistanceCalculator::bearing($london, $paris);
assertTest($bearing > 145.0 && $bearing < 165.0, "Bearing from London to Paris is south-east (~150 deg)");

// Destination projection: travel 100 km due North (bearing 0)
$startPoint = new Point(23.00, 90.00);
$projDest = DistanceCalculator::destination($startPoint, 100000.0, 0.0);
$checkDist = DistanceCalculator::haversine($startPoint, $projDest);
assertTest(abs($checkDist - 100000.0) < 50.0, "Destination projection accurately lands at 100 km distance");
assertTest($projDest->getLat() > 23.00, "Heading North increases latitude");

// -----------------------------------------------------------------------------
// 4. 2D R-Tree Spatial Indexing & KNN Search
// -----------------------------------------------------------------------------
$rtree = new RTree(maxEntries: 4, minEntries: 2);

$points = [
    ['id' => 'p1', 'lat' => 10.0, 'lng' => 10.0],
    ['id' => 'p2', 'lat' => 10.1, 'lng' => 10.1],
    ['id' => 'p3', 'lat' => 20.0, 'lng' => 20.0],
    ['id' => 'p4', 'lat' => 20.5, 'lng' => 20.5],
    ['id' => 'p5', 'lat' => 50.0, 'lng' => 50.0],
    ['id' => 'p6', 'lat' => 50.1, 'lng' => 50.1],
];

foreach ($points as $p) {
    $rtree->insertPoint(new Point($p['lat'], $p['lng']), $p, $p['id']);
}

assertTest($rtree->count() === 6, "RTree contains 6 indexed items");

// Search intersecting box [9, 9] to [11, 11]
$searchBox = new BoundingBox(9.0, 9.0, 11.0, 11.0);
$found = $rtree->search($searchBox);
assertTest(count($found) === 2, "RTree search returns exactly 2 points in [9, 9] to [11, 11]");

// K-Nearest Neighbors (KNN) to [10.05, 10.05]
$knnTarget = new Point(10.05, 10.05);
$neighbors = $rtree->knn($knnTarget, k: 3);
assertTest(count($neighbors) === 3, "RTree KNN returns 3 closest neighbors");
assertTest($neighbors[0]['id'] === 'p1' || $neighbors[0]['id'] === 'p2', "Closest neighbor is p1 or p2");
assertTest($neighbors[0]['distanceMeters'] <= $neighbors[1]['distanceMeters'], "KNN results sorted in ascending distance");

// Removal
$removed = $rtree->remove('p5');
assertTest($removed === true && $rtree->count() === 5, "RTree remove deletes entry and decrements count");

// -----------------------------------------------------------------------------
// 5. Uber H3 Hexagonal Discrete Global Grid
// -----------------------------------------------------------------------------
$h3Cell = H3Index::geoToH3(23.8103, 90.4125, resolution: 8);
assertTest(is_string($h3Cell) && strlen($h3Cell) >= 15, "H3Index produces 15-character hex string");
assertTest(H3Index::h3IsValid($h3Cell) === true, "H3Index validates generated cell index");
assertTest(H3Index::h3GetResolution($h3Cell) === 8, "H3Index resolution matches (8)");

$centroid = H3Index::h3ToGeo($h3Cell);
$centroidDist = DistanceCalculator::haversine(new Point(23.8103, 90.4125), $centroid);
assertTest($centroidDist < 2000.0, "Decoded H3 centroid is within 2km neighborhood of original coordinate");

$boundary = H3Index::h3ToGeoBoundary($h3Cell);
assertTest($boundary instanceof Polygon, "H3 boundary is a Polygon");
assertTest(count($boundary->getExteriorRing()) === 7, "H3 hexagonal boundary has 6 vertices + 1 closing vertex");

// k-Ring neighbors
$kRing1 = H3Index::kRing($h3Cell, k: 1);
assertTest(count($kRing1) >= 6, "kRing(k=1) returns 6-7 neighboring hexagonal cells");
assertTest(in_array($h3Cell, $kRing1), "kRing includes origin cell");

// Grid distance
$h3Dist = H3Index::h3Distance($h3Cell, $h3Cell);
assertTest($h3Dist === 0, "H3 distance from cell to itself is 0 steps");

// -----------------------------------------------------------------------------
// 6. Point-in-Polygon & High-Velocity Geo-Fencing
// -----------------------------------------------------------------------------
// Polygon [0,0] to [10,10] with hole [4,4] to [6,6]
$testPoly = new Polygon([
    new Point(0.0, 0.0),
    new Point(0.0, 10.0),
    new Point(10.0, 10.0),
    new Point(10.0, 0.0),
], [
    [
        new Point(4.0, 4.0),
        new Point(4.0, 6.0),
        new Point(6.0, 6.0),
        new Point(6.0, 4.0),
    ]
]);

assertTest(RayCaster::contains($testPoly, new Point(2.0, 2.0)) === true, "RayCaster: point inside polygon body returns true");
assertTest(RayCaster::contains($testPoly, new Point(5.0, 5.0)) === false, "RayCaster: point inside polygon hole returns false");
assertTest(RayCaster::contains($testPoly, new Point(15.0, 15.0)) === false, "RayCaster: point outside polygon bbox returns false");

// GeoFence Circular & Polygon entities
$circularFence = GeoFence::circle('fence-circle', 'Airport Security Buffer', new Point(23.8433, 90.3978), radiusMeters: 2000.0);
assertTest($circularFence->isCircular() === true, "GeoFence recognizes circular fence");
assertTest($circularFence->containsPoint(new Point(23.8450, 90.3980)) === true, "Point inside 2km circular fence returns true");
assertTest($circularFence->containsPoint(new Point(23.9500, 90.5000)) === false, "Point outside 2km circular fence returns false");

// GeoFenceMonitor (ENTER, DWELL, EXIT transitions)
$monitor = new GeoFenceMonitor();
$monitor->addFence($circularFence);

// Step 1: Vehicle outside fence
$eventsStep1 = $monitor->updatePosition('truck-1', new Point(23.9500, 90.5000), timestamp: 1000);
assertTest(empty($eventsStep1), "No events triggered when entity is outside all fences");

// Step 2: Vehicle enters fence -> ENTER
$eventsStep2 = $monitor->updatePosition('truck-1', new Point(23.8450, 90.3980), timestamp: 1010);
assertTest(count($eventsStep2) === 1, "One event triggered on entering fence");
assertTest($eventsStep2[0]['event'] === GeoFenceEvent::ENTER, "Triggered event is ENTER");

// Step 3: Vehicle stays inside fence -> DWELL
$eventsStep3 = $monitor->updatePosition('truck-1', new Point(23.8440, 90.3975), timestamp: 1020);
assertTest(count($eventsStep3) === 1, "One event triggered while dwelling in fence");
assertTest($eventsStep3[0]['event'] === GeoFenceEvent::DWELL, "Triggered event is DWELL");

// Step 4: Vehicle leaves fence -> EXIT
$eventsStep4 = $monitor->updatePosition('truck-1', new Point(23.9900, 90.6000), timestamp: 1030);
assertTest(count($eventsStep4) === 1, "One event triggered on leaving fence");
assertTest($eventsStep4[0]['event'] === GeoFenceEvent::EXIT, "Triggered event is EXIT");

// -----------------------------------------------------------------------------
// 7. GeoSpatialManager Master Facade & Global Helpers
// -----------------------------------------------------------------------------
$manager = new GeoSpatialManager();
$distFacade = $manager->distance($london, $paris, method: 'vincenty');
assertTest($distFacade > 340000, "GeoSpatialManager calculates Vincenty distance");

$h3Facade = $manager->geoToH3(23.8103, 90.4125);
assertTest(H3Index::h3IsValid($h3Facade) === true, "GeoSpatialManager encodes H3 cell");

// Global helpers
assertTest(function_exists('geo') && function_exists('geo_distance') && function_exists('h3_index') && function_exists('geo_fence'), "Global geospatial helpers exist");
$helperDist = geo_distance($london, $paris);
assertTest($helperDist > 340000, "geo_distance() helper works");

$helperH3 = h3_index(23.8103, 90.4125);
assertTest(is_string($helperH3), "h3_index() helper works");

// -----------------------------------------------------------------------------
// 8. Database Migration (spatial_geofences, spatial_tracked_entities, spatial_geofence_events)
// -----------------------------------------------------------------------------
$dbConn = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
$pdo = $dbConn->getPdo();

$migration = require __DIR__ . '/../database/migrations/2026_09_28_000072_create_geospatial_tables.php';
$migration->up($dbConn);

$stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name IN ('spatial_geofences', 'spatial_tracked_entities', 'spatial_geofence_events')");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
assertTest(count($tables) === 3, "Database migration created spatial_geofences, spatial_tracked_entities, spatial_geofence_events tables");

// Insert and verify
$pdo->exec("INSERT INTO spatial_geofences (id, name, type, geometry_geojson) VALUES ('fence-test', 'Test Zone', 'polygon', '{\"type\":\"Polygon\"}')");
$row = $pdo->query("SELECT * FROM spatial_geofences WHERE id = 'fence-test'")->fetch(PDO::FETCH_ASSOC);
assertTest($row['name'] === 'Test Zone', "Geo-fence record inserted and retrieved from database");

// Rollback
$migration->down($dbConn);
$remainingTables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name IN ('spatial_geofences', 'spatial_tracked_entities', 'spatial_geofence_events')")->fetchAll(PDO::FETCH_COLUMN);
assertTest(count($remainingTables) === 0, "Migration down successfully drops geospatial tables");

echo "\n========================================================\n";
echo " Phase 89 Test Summary: {$passed} Passed, {$failed} Failed\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
