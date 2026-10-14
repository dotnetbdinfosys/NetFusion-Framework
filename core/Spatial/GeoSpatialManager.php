<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\Spatial;

use NetFusion\Core\Spatial\Geodesic\DistanceCalculator;
use NetFusion\Core\Spatial\GeoFence\GeoFence;
use NetFusion\Core\Spatial\GeoFence\GeoFenceMonitor;
use NetFusion\Core\Spatial\Geometry\BoundingBox;
use NetFusion\Core\Spatial\Geometry\GeoJson;
use NetFusion\Core\Spatial\Geometry\LineString;
use NetFusion\Core\Spatial\Geometry\Point;
use NetFusion\Core\Spatial\Geometry\Polygon;
use NetFusion\Core\Spatial\H3\H3Index;
use NetFusion\Core\Spatial\Index\RTree;

/**
 * Enterprise Geospatial Engine & Spatial Indexing Manager.
 * Orchestrates geodesic calculations, 2D R-Tree spatial indexing,
 * Uber H3 hexagonal discrete global grid, and high-velocity geo-fencing.
 */
class GeoSpatialManager
{
    /** @var array<string, mixed> */
    protected array $config;

    protected GeoFenceMonitor $monitor;
    protected RTree $defaultRTree;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'default_unit' => 'meters',
            'earth_radius' => 6371000.0,
            'default_h3_resolution' => 8,
            'rtree_max_entries' => 16,
            'rtree_min_entries' => 4,
        ], $config);

        $this->monitor = new GeoFenceMonitor();
        $this->defaultRTree = new RTree(
            (int) $this->config['rtree_max_entries'],
            (int) $this->config['rtree_min_entries']
        );
    }

    /**
     * Calculate geodesic distance between two points (in meters).
     */
    public function distance(Point $p1, Point $p2, string $method = 'haversine'): float
    {
        if (strtolower($method) === 'vincenty') {
            return DistanceCalculator::vincenty($p1, $p2);
        }

        return DistanceCalculator::haversine($p1, $p2, (float) $this->config['earth_radius']);
    }

    /**
     * Calculate initial bearing from p1 to p2 in degrees [0, 360).
     */
    public function bearing(Point $p1, Point $p2): float
    {
        return DistanceCalculator::bearing($p1, $p2);
    }

    /**
     * Project destination point from start point given distance and bearing.
     */
    public function destination(Point $start, float $distanceMeters, float $bearingDegrees): Point
    {
        return DistanceCalculator::destination($start, $distanceMeters, $bearingDegrees, (float) $this->config['earth_radius']);
    }

    /**
     * Create an isolated R-Tree spatial index.
     */
    public function createRTree(?int $maxEntries = null, ?int $minEntries = null): RTree
    {
        return new RTree(
            $maxEntries ?? (int) $this->config['rtree_max_entries'],
            $minEntries ?? (int) $this->config['rtree_min_entries']
        );
    }

    public function getRTree(): RTree
    {
        return $this->defaultRTree;
    }

    /**
     * Convert geographic coordinates to H3 hexagonal index.
     */
    public function geoToH3(float $lat, float $lng, ?int $resolution = null): string
    {
        return H3Index::geoToH3($lat, $lng, $resolution ?? (int) $this->config['default_h3_resolution']);
    }

    /**
     * Decode H3 index to geographic Point.
     */
    public function h3ToGeo(string $h3Index): Point
    {
        return H3Index::h3ToGeo($h3Index);
    }

    /**
     * Get hexagonal boundary polygon of an H3 cell.
     */
    public function h3ToGeoBoundary(string $h3Index): Polygon
    {
        return H3Index::h3ToGeoBoundary($h3Index);
    }

    /**
     * Get neighboring cells within k distance rings.
     *
     * @return array<int, string>
     */
    public function kRing(string $h3Index, int $k = 1): array
    {
        return H3Index::kRing($h3Index, $k);
    }

    public function h3Distance(string $origin, string $destination): int
    {
        return H3Index::h3Distance($origin, $destination);
    }

    public function monitor(): GeoFenceMonitor
    {
        return $this->monitor;
    }

    /**
     * Quick factory for creating and registering a GeoFence.
     */
    public function createFence(
        string $id,
        string $name,
        Polygon|Point $geometry,
        ?float $radiusMeters = null,
        array $metadata = []
    ): GeoFence {
        $fence = new GeoFence($id, $name, $geometry, $radiusMeters, $metadata);
        $this->monitor->addFence($fence);
        return $fence;
    }

    /**
     * Parse standard GeoJSON.
     */
    public function parseGeoJson(string|array $input): mixed
    {
        return GeoJson::parse($input);
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}
