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

/**
 * Spatial Minimum Bounding Box (MBR / BBox).
 * Defines spatial bounds with min/max latitude and longitude.
 */
class BoundingBox
{
    public readonly float $minLat;
    public readonly float $minLng;
    public readonly float $maxLat;
    public readonly float $maxLng;

    public function __construct(float $minLat, float $minLng, float $maxLat, float $maxLng)
    {
        if ($minLat > $maxLat) {
            throw new InvalidArgumentException("minLat cannot be greater than maxLat.");
        }
        if ($minLng > $maxLng) {
            throw new InvalidArgumentException("minLng cannot be greater than maxLng.");
        }

        $this->minLat = $minLat;
        $this->minLng = $minLng;
        $this->maxLat = $maxLat;
        $this->maxLng = $maxLng;
    }

    public function getMinLat(): float
    {
        return $this->minLat;
    }

    public function getMinLng(): float
    {
        return $this->minLng;
    }

    public function getMaxLat(): float
    {
        return $this->maxLat;
    }

    public function getMaxLng(): float
    {
        return $this->maxLng;
    }

    public function containsPoint(Point $p): bool
    {
        return $p->getLat() >= $this->minLat &&
               $p->getLat() <= $this->maxLat &&
               $p->getLng() >= $this->minLng &&
               $p->getLng() <= $this->maxLng;
    }

    public function containsBox(self $box): bool
    {
        return $box->minLat >= $this->minLat &&
               $box->maxLat <= $this->maxLat &&
               $box->minLng >= $this->minLng &&
               $box->maxLng <= $this->maxLng;
    }

    public function intersects(self $other): bool
    {
        return !($this->maxLat < $other->minLat ||
                 $this->minLat > $other->maxLat ||
                 $this->maxLng < $other->minLng ||
                 $this->minLng > $other->maxLng);
    }

    /**
     * Compute union bounding box enclosing both this and another box or point.
     */
    public function expand(self|Point $target): self
    {
        if ($target instanceof Point) {
            return new self(
                min($this->minLat, $target->getLat()),
                min($this->minLng, $target->getLng()),
                max($this->maxLat, $target->getLat()),
                max($this->maxLng, $target->getLng())
            );
        }

        return new self(
            min($this->minLat, $target->minLat),
            min($this->minLng, $target->minLng),
            max($this->maxLat, $target->maxLat),
            max($this->maxLng, $target->maxLng)
        );
    }

    public function getCenter(): Point
    {
        return new Point(
            ($this->minLat + $this->maxLat) / 2.0,
            ($this->minLng + $this->maxLng) / 2.0
        );
    }

    /**
     * Area in degree units (used for R-tree cost estimation).
     */
    public function getArea(): float
    {
        $dLat = $this->maxLat - $this->minLat;
        $dLng = $this->maxLng - $this->minLng;
        return max(0.0, $dLat * $dLng);
    }

    /**
     * @param array<Point> $points
     */
    public static function fromPoints(array $points): self
    {
        if (empty($points)) {
            return new self(0.0, 0.0, 0.0, 0.0);
        }

        $minLat = $points[0]->getLat();
        $maxLat = $points[0]->getLat();
        $minLng = $points[0]->getLng();
        $maxLng = $points[0]->getLng();

        foreach ($points as $p) {
            if ($p->getLat() < $minLat) $minLat = $p->getLat();
            if ($p->getLat() > $maxLat) $maxLat = $p->getLat();
            if ($p->getLng() < $minLng) $minLng = $p->getLng();
            if ($p->getLng() > $maxLng) $maxLng = $p->getLng();
        }

        return new self($minLat, $minLng, $maxLat, $maxLng);
    }

    /**
     * Convert to standard GeoJSON bbox array: [west, south, east, north] = [minLng, minLat, maxLng, maxLat].
     *
     * @return array<int, float>
     */
    public function toGeoJson(): array
    {
        return [$this->minLng, $this->minLat, $this->maxLng, $this->maxLat];
    }
}
