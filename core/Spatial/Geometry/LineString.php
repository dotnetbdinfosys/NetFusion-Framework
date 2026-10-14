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
 * Spatial LineString: An ordered sequence of geographic points representing a continuous path.
 */
class LineString
{
    /** @var array<Point> */
    protected array $points;

    /**
     * @param array<Point> $points
     */
    public function __construct(array $points)
    {
        if (count($points) < 2) {
            throw new InvalidArgumentException("LineString must contain at least 2 points.");
        }

        $this->points = array_values($points);
    }

    /**
     * @return array<Point>
     */
    public function getPoints(): array
    {
        return $this->points;
    }

    public function count(): int
    {
        return count($this->points);
    }

    public function getStartPoint(): Point
    {
        return $this->points[0];
    }

    public function getEndPoint(): Point
    {
        return $this->points[count($this->points) - 1];
    }

    public function getBoundingBox(): BoundingBox
    {
        return BoundingBox::fromPoints($this->points);
    }

    public function getType(): GeometryType
    {
        return GeometryType::LINE_STRING;
    }

    /**
     * GeoJSON coordinate representation: [[lng, lat], [lng, lat], ...]
     *
     * @return array<int, array<int, float>>
     */
    public function getCoordinates(): array
    {
        return array_map(fn(Point $p) => $p->getCoordinates(), $this->points);
    }

    /**
     * @return array{type: string, coordinates: array<int, array<int, float>>}
     */
    public function toGeoJson(): array
    {
        return [
            'type' => GeometryType::LINE_STRING->value,
            'coordinates' => $this->getCoordinates(),
        ];
    }

    /**
     * WKT format: LINESTRING(lng lat, lng lat, ...)
     */
    public function toWkt(): string
    {
        $pairs = array_map(fn(Point $p) => sprintf("%f %f", $p->getLng(), $p->getLat()), $this->points);
        return sprintf("LINESTRING(%s)", implode(', ', $pairs));
    }
}
