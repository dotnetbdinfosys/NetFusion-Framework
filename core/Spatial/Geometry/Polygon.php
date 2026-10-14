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

namespace NetFusion\Core\Spatial\Geometry;

use InvalidArgumentException;
use NetFusion\Core\Spatial\Enums\GeometryType;

/**
 * Spatial 2D Polygon with an exterior linear ring and optional interior rings (holes).
 * Compliant with RFC 7946 GeoJSON and OGC SFA.
 */
class Polygon
{
    /** @var array<Point> */
    protected array $exteriorRing;

    /** @var array<array<Point>> */
    protected array $interiorRings = [];

    /**
     * @param array<Point> $exteriorRing
     * @param array<array<Point>> $interiorRings
     */
    public function __construct(array $exteriorRing, array $interiorRings = [])
    {
        $this->exteriorRing = $this->ensureClosedRing($exteriorRing);

        if (count($this->exteriorRing) < 4) {
            throw new InvalidArgumentException("Polygon exterior ring must have at least 4 points (3 vertices + closing point).");
        }

        foreach ($interiorRings as $hole) {
            $closedHole = $this->ensureClosedRing($hole);
            if (count($closedHole) >= 4) {
                $this->interiorRings[] = $closedHole;
            }
        }
    }

    /**
     * Ensure linear ring starts and ends with the exact same coordinate.
     *
     * @param array<Point> $ring
     * @return array<Point>
     */
    protected function ensureClosedRing(array $ring): array
    {
        if (empty($ring)) {
            return [];
        }

        $ring = array_values($ring);
        $first = $ring[0];
        $last = $ring[count($ring) - 1];

        if (abs($first->getLat() - $last->getLat()) > 1e-9 || abs($first->getLng() - $last->getLng()) > 1e-9) {
            $ring[] = new Point($first->getLat(), $first->getLng(), $first->getAltitude());
        }

        return $ring;
    }

    /**
     * @return array<Point>
     */
    public function getExteriorRing(): array
    {
        return $this->exteriorRing;
    }

    /**
     * @return array<array<Point>>
     */
    public function getInteriorRings(): array
    {
        return $this->interiorRings;
    }

    public function hasHoles(): bool
    {
        return !empty($this->interiorRings);
    }

    public function getBoundingBox(): BoundingBox
    {
        return BoundingBox::fromPoints($this->exteriorRing);
    }

    public function getType(): GeometryType
    {
        return GeometryType::POLYGON;
    }

    /**
     * GeoJSON coordinate representation: [[[lng, lat], ...], ...]
     *
     * @return array<int, array<int, array<int, float>>>
     */
    public function getCoordinates(): array
    {
        $rings = [
            array_map(fn(Point $p) => $p->getCoordinates(), $this->exteriorRing),
        ];

        foreach ($this->interiorRings as $hole) {
            $rings[] = array_map(fn(Point $p) => $p->getCoordinates(), $hole);
        }

        return $rings;
    }

    /**
     * @return array{type: string, coordinates: array<int, array<int, array<int, float>>>}
     */
    public function toGeoJson(): array
    {
        return [
            'type' => GeometryType::POLYGON->value,
            'coordinates' => $this->getCoordinates(),
        ];
    }

    /**
     * WKT format: POLYGON((lng lat, ...), (lng lat, ...))
     */
    public function toWkt(): string
    {
        $ringStrings = [];
        $extPairs = array_map(fn(Point $p) => sprintf("%f %f", $p->getLng(), $p->getLat()), $this->exteriorRing);
        $ringStrings[] = sprintf("(%s)", implode(', ', $extPairs));

        foreach ($this->interiorRings as $hole) {
            $holePairs = array_map(fn(Point $p) => sprintf("%f %f", $p->getLng(), $p->getLat()), $hole);
            $ringStrings[] = sprintf("(%s)", implode(', ', $holePairs));
        }

        return sprintf("POLYGON(%s)", implode(', ', $ringStrings));
    }
}
