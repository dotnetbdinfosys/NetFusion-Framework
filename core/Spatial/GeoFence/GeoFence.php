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

namespace NetFusion\Core\Spatial\GeoFence;

use NetFusion\Core\Spatial\Geodesic\DistanceCalculator;
use NetFusion\Core\Spatial\Geometry\BoundingBox;
use NetFusion\Core\Spatial\Geometry\Point;
use NetFusion\Core\Spatial\Geometry\Polygon;

/**
 * Spatial Geo-Fence Entity.
 * Supports both arbitrary polygonal boundaries (with holes) and radial circular buffer zones.
 */
class GeoFence
{
    protected string $id;
    protected string $name;
    protected Polygon|Point $geometry;
    protected ?float $radiusMeters;
    /** @var array<string, mixed> */
    protected array $metadata;

    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        string $id,
        string $name,
        Polygon|Point $geometry,
        ?float $radiusMeters = null,
        array $metadata = []
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->geometry = $geometry;
        $this->radiusMeters = $radiusMeters;
        $this->metadata = $metadata;
    }

    public static function polygon(string $id, string $name, Polygon $polygon, array $metadata = []): self
    {
        return new self($id, $name, $polygon, null, $metadata);
    }

    public static function circle(string $id, string $name, Point $center, float $radiusMeters, array $metadata = []): self
    {
        return new self($id, $name, $center, $radiusMeters, $metadata);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isCircular(): bool
    {
        return $this->geometry instanceof Point && $this->radiusMeters !== null;
    }

    public function getGeometry(): Polygon|Point
    {
        return $this->geometry;
    }

    public function getRadiusMeters(): ?float
    {
        return $this->radiusMeters;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function containsPoint(Point $point): bool
    {
        if ($this->isCircular()) {
            /** @var Point $center */
            $center = $this->geometry;
            $dist = DistanceCalculator::haversine($center, $point);
            return $dist <= $this->radiusMeters;
        }

        /** @var Polygon $polygon */
        $polygon = $this->geometry;
        return RayCaster::contains($polygon, $point);
    }

    public function getBoundingBox(): BoundingBox
    {
        if ($this->geometry instanceof Polygon) {
            return $this->geometry->getBoundingBox();
        }

        // Circular bounding box
        /** @var Point $center */
        $center = $this->geometry;
        $r = $this->radiusMeters ?? 100.0;
        $dLat = rad2deg($r / 6371000.0);
        $dLng = rad2deg($r / (6371000.0 * cos(deg2rad($center->getLat()))));

        return new BoundingBox(
            $center->getLat() - $dLat,
            $center->getLng() - $dLng,
            $center->getLat() + $dLat,
            $center->getLng() + $dLng
        );
    }
}
