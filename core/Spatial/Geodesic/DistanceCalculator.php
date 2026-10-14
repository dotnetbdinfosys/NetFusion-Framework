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

namespace NetFusion\Core\Spatial\Geodesic;

use NetFusion\Core\Spatial\Geometry\Point;

/**
 * High-Precision Geodesic Calculator.
 * Supports Haversine spherical distance, Vincenty WGS-84 ellipsoidal inverse,
 * forward bearing, and destination point projection.
 */
class DistanceCalculator
{
    public const EARTH_RADIUS_METERS = 6371000.0; // Mean spherical radius
    public const WGS84_A = 6378137.0;             // Semi-major axis (meters)
    public const WGS84_B = 6356752.314245;        // Semi-minor axis (meters)
    public const WGS84_F = 1.0 / 298.257223563;   // Flattening

    /**
     * Compute Great-Circle distance using Haversine formula (meters).
     */
    public static function haversine(Point $p1, Point $p2, float $radius = self::EARTH_RADIUS_METERS): float
    {
        $lat1 = deg2rad($p1->getLat());
        $lat2 = deg2rad($p2->getLat());
        $dLat = deg2rad($p2->getLat() - $p1->getLat());
        $dLng = deg2rad($p2->getLng() - $p1->getLng());

        $a = sin($dLat / 2.0) ** 2 + cos($lat1) * cos($lat2) * (sin($dLng / 2.0) ** 2);
        $c = 2.0 * atan2(sqrt($a), sqrt(max(0.0, 1.0 - $a)));

        return $radius * $c;
    }

    /**
     * Compute geodesic distance on WGS-84 ellipsoid using Vincenty's inverse formula (sub-millimeter accuracy).
     * Falls back to Haversine on nearly antipodal points where Vincenty may fail to converge.
     */
    public static function vincenty(Point $p1, Point $p2): float
    {
        $lat1 = deg2rad($p1->getLat());
        $lon1 = deg2rad($p1->getLng());
        $lat2 = deg2rad($p2->getLat());
        $lon2 = deg2rad($p2->getLng());

        // Coincident points
        if (abs($lat1 - $lat2) < 1e-12 && abs($lon1 - $lon2) < 1e-12) {
            return 0.0;
        }

        $a = self::WGS84_A;
        $b = self::WGS84_B;
        $f = self::WGS84_F;

        $L = $lon2 - $lon1;
        $U1 = atan((1.0 - $f) * tan($lat1));
        $U2 = atan((1.0 - $f) * tan($lat2));
        $sinU1 = sin($U1);
        $cosU1 = cos($U1);
        $sinU2 = sin($U2);
        $cosU2 = cos($U2);

        $lambda = $L;
        $lambdaPrev = 0.0;
        $iterLimit = 100;

        do {
            $sinLambda = sin($lambda);
            $cosLambda = cos($lambda);
            $sinSigma = sqrt(($cosU2 * $sinLambda) ** 2 + ($cosU1 * $sinU2 - $sinU1 * $cosU2 * $cosLambda) ** 2);

            if ($sinSigma == 0.0) {
                return 0.0; // Coincident points
            }

            $cosSigma = $sinU1 * $sinU2 + $cosU1 * $cosU2 * $cosLambda;
            $sigma = atan2($sinSigma, $cosSigma);
            $sinAlpha = ($cosU1 * $cosU2 * $sinLambda) / $sinSigma;
            $cosSqAlpha = 1.0 - $sinAlpha ** 2;

            $cos2SigmaM = ($cosSqAlpha != 0.0) ? ($cosSigma - 2.0 * $sinU1 * $sinU2 / $cosSqAlpha) : 0.0;
            $C = ($f / 16.0) * $cosSqAlpha * (4.0 + $f * (4.0 - 3.0 * $cosSqAlpha));

            $lambdaPrev = $lambda;
            $lambda = $L + (1.0 - $C) * $f * $sinAlpha * ($sigma + $C * $sinSigma * ($cos2SigmaM + $C * $cosSigma * (-1.0 + 2.0 * ($cos2SigmaM ** 2))));

        } while (abs($lambda - $lambdaPrev) > 1e-12 && --$iterLimit > 0);

        if ($iterLimit === 0) {
            // Formula failed to converge (nearly antipodal), fallback to Haversine
            return self::haversine($p1, $p2);
        }

        $uSq = $cosSqAlpha * ($a ** 2 - $b ** 2) / ($b ** 2);
        $A = 1.0 + ($uSq / 16384.0) * (4096.0 + $uSq * (-768.0 + $uSq * (320.0 - 175.0 * $uSq)));
        $B = ($uSq / 1024.0) * (256.0 + $uSq * (-128.0 + $uSq * (74.0 - 47.0 * $uSq)));
        $deltaSigma = $B * $sinSigma * ($cos2SigmaM + ($B / 4.0) * ($cosSigma * (-1.0 + 2.0 * ($cos2SigmaM ** 2)) - ($B / 6.0) * $cos2SigmaM * (-3.0 + 4.0 * ($sinSigma ** 2)) * (-3.0 + 4.0 * ($cos2SigmaM ** 2))));

        return $b * $A * ($sigma - $deltaSigma);
    }

    /**
     * Compute initial compass bearing / azimuth from p1 to p2 in degrees [0, 360).
     */
    public static function bearing(Point $p1, Point $p2): float
    {
        $lat1 = deg2rad($p1->getLat());
        $lat2 = deg2rad($p2->getLat());
        $dLng = deg2rad($p2->getLng() - $p1->getLng());

        $y = sin($dLng) * cos($lat2);
        $x = cos($lat1) * sin($lat2) - sin($lat1) * cos($lat2) * cos($dLng);
        $bearingRad = atan2($y, $x);

        $bearingDeg = rad2deg($bearingRad);
        return fmod($bearingDeg + 360.0, 360.0);
    }

    /**
     * Project a new Point given start location, distance in meters, and bearing in degrees.
     */
    public static function destination(
        Point $start,
        float $distanceMeters,
        float $bearingDegrees,
        float $radius = self::EARTH_RADIUS_METERS
    ): Point {
        $lat1 = deg2rad($start->getLat());
        $lng1 = deg2rad($start->getLng());
        $brng = deg2rad($bearingDegrees);
        $angularDist = $distanceMeters / $radius;

        $lat2 = asin(sin($lat1) * cos($angularDist) + cos($lat1) * sin($angularDist) * cos($brng));
        $lng2 = $lng1 + atan2(sin($brng) * sin($angularDist) * cos($lat1), cos($angularDist) - sin($lat1) * sin($lat2));

        return new Point(
            rad2deg($lat2),
            fmod(rad2deg($lng2) + 540.0, 360.0) - 180.0
        );
    }
}
