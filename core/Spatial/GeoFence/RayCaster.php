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

use NetFusion\Core\Spatial\Geometry\Point;
use NetFusion\Core\Spatial\Geometry\Polygon;

/**
 * High-Performance Point-in-Polygon (PIP) Containment Evaluator.
 * Implements Ray Casting (Even-Odd rule) with Bounding Box short-circuiting
 * and multi-ring hole exclusion.
 */
class RayCaster
{
    /**
     * Test whether a geographic Point lies strictly inside a Polygon (accounting for holes).
     */
    public static function contains(Polygon $polygon, Point $point): bool
    {
        // 1. Fast Bounding Box short-circuit rejection
        if (!$polygon->getBoundingBox()->containsPoint($point)) {
            return false;
        }

        // 2. Check exterior linear ring
        if (!self::pointInRing($point, $polygon->getExteriorRing())) {
            return false;
        }

        // 3. Check interior rings (holes): if point is inside any hole, it is outside the polygon
        foreach ($polygon->getInteriorRings() as $hole) {
            if (self::pointInRing($point, $hole)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Ray Casting algorithm for a single closed linear ring of Points.
     * Casts an imaginary horizontal ray eastward from the test point and counts edge intersections.
     *
     * @param array<Point> $ring
     */
    public static function pointInRing(Point $point, array $ring): bool
    {
        $n = count($ring);
        if ($n < 3) {
            return false;
        }

        $inside = false;
        $px = $point->getLng();
        $py = $point->getLat();

        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $xi = $ring[$i]->getLng();
            $yi = $ring[$i]->getLat();
            $xj = $ring[$j]->getLng();
            $yj = $ring[$j]->getLat();

            // Check if horizontal ray crosses segment (j, i)
            $intersect = (($yi > $py) !== ($yj > $py)) &&
                         ($px < ($xj - $xi) * ($py - $yi) / ($yj - $yi) + $xi);

            if ($intersect) {
                $inside = !$inside;
            }
        }

        return $inside;
    }
}
