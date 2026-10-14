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

namespace NetFusion\Core\Spatial\H3;

use InvalidArgumentException;
use NetFusion\Core\Spatial\Geodesic\DistanceCalculator;
use NetFusion\Core\Spatial\Geometry\Point;
use NetFusion\Core\Spatial\Geometry\Polygon;

/**
 * Pure PHP Hexagonal Hierarchical Spatial Index (inspired by Uber H3).
 * Encodes geographic coordinates into 64-bit hexadecimal discrete global grid cells
 * across 16 hierarchical resolutions (0 to 15).
 */
class H3Index
{
    public const H3_CELL_MODE = 1;
    public const MAX_RESOLUTION = 15;
    public const NUM_BASE_CELLS = 122;

    /**
     * Approximate average hexagon edge lengths in meters per resolution level (0-15).
     */
    public const RES_EDGE_LENGTHS = [
        0 => 1107712.59,
        1 => 418676.0,
        2 => 158244.0,
        3 => 59810.0,
        4 => 22606.0,
        5 => 8544.0,
        6 => 3229.0,
        7 => 1220.6,
        8 => 461.35,
        9 => 174.37,
        10 => 65.91,
        11 => 24.91,
        12 => 9.41,
        13 => 3.56,
        14 => 1.34,
        15 => 0.51,
    ];

    /**
     * Encode geographic coordinates (Latitude, Longitude) into an H3 hexadecimal index.
     *
     * @param float $lat Latitude in degrees [-90, 90]
     * @param float $lng Longitude in degrees [-180, 180]
     * @param int $resolution Resolution level [0, 15]
     * @return string 15-character lowercase hexadecimal H3 index (e.g. '8828308281fffff')
     */
    public static function geoToH3(float $lat, float $lng, int $resolution = 8): string
    {
        if ($resolution < 0 || $resolution > self::MAX_RESOLUTION) {
            throw new InvalidArgumentException("H3 resolution must be between 0 and 15.");
        }

        $normLat = ($lat + 90.0) / 180.0;   // [0, 1]
        $normLng = ($lng + 180.0) / 360.0;  // [0, 1]

        $latBand = min(10, (int) floor($normLat * 11)); // 0 to 10
        $lngSector = min(10, (int) floor($normLng * 11)); // 0 to 10
        $baseCell = ($latBand * 11 + $lngSector) % self::NUM_BASE_CELLS;

        $u = ($normLat * 11.0) - $latBand;
        $v = ($normLng * 11.0) - $lngSector;

        // Construct 64-bit integer bit fields:
        // Bit 63: 0
        // Bits 59-62: Mode (1)
        // Bits 56-58: Reserved (0)
        // Bits 52-55: Resolution (0-15)
        // Bits 45-51: Base Cell (0-121)
        $h3 = (self::H3_CELL_MODE << 59) | ($resolution << 52) | (($baseCell & 0x7F) << 45);

        for ($res = 1; $res <= self::MAX_RESOLUTION; $res++) {
            if ($res <= $resolution) {
                $u = max(0.0, min(0.999999999, $u));
                $v = max(0.0, min(0.999999999, $v));

                if ($u >= 1.0 / 3.0 && $u < 2.0 / 3.0) {
                    if ($v < 1.0 / 3.0) {
                        $digit = 5;
                        $u = ($u - 1.0 / 3.0) * 3.0;
                        $v = $v * 3.0;
                    } elseif ($v >= 2.0 / 3.0) {
                        $digit = 6;
                        $u = ($u - 1.0 / 3.0) * 3.0;
                        $v = ($v - 2.0 / 3.0) * 3.0;
                    } else {
                        $digit = 0;
                        $u = ($u - 1.0 / 3.0) * 3.0;
                        $v = ($v - 1.0 / 3.0) * 3.0;
                    }
                } elseif ($u < 1.0 / 3.0) {
                    if ($v < 0.5) {
                        $digit = 1;
                        $u = $u * 3.0;
                        $v = $v * 2.0;
                    } else {
                        $digit = 2;
                        $u = $u * 3.0;
                        $v = ($v - 0.5) * 2.0;
                    }
                } else {
                    if ($v < 0.5) {
                        $digit = 3;
                        $u = ($u - 2.0 / 3.0) * 3.0;
                        $v = $v * 2.0;
                    } else {
                        $digit = 4;
                        $u = ($u - 2.0 / 3.0) * 3.0;
                        $v = ($v - 0.5) * 2.0;
                    }
                }
            } else {
                $digit = 7; // Unused resolution levels must be set to 7 (all 1s)
            }

            $bitOffset = 45 - ($res * 3);
            $h3 |= (($digit & 0x07) << $bitOffset);
        }

        // Return lowercase 15-char hex string (omitting leading zero nibble)
        return substr(sprintf('%016x', $h3), 1);
    }

    /**
     * Decode an H3 hexadecimal index to its approximate geographic centroid (Point).
     */
    public static function h3ToGeo(string $h3Index): Point
    {
        $parsed = self::parseH3(trim(strtolower($h3Index)));
        $baseCell = $parsed['baseCell'];
        $resolution = $parsed['resolution'];
        $digits = $parsed['digits'];

        // Base cell boundaries
        $latBand = (int) floor($baseCell / 11);
        $lngSector = $baseCell % 11;

        $latMin = ($latBand / 11.0) * 180.0 - 90.0;
        $latMax = (($latBand + 1) / 11.0) * 180.0 - 90.0;
        $lngMin = ($lngSector / 11.0) * 360.0 - 180.0;
        $lngMax = (($lngSector + 1) / 11.0) * 360.0 - 180.0;

        for ($r = 1; $r <= $resolution; $r++) {
            $d = $digits[$r - 1] ?? 7;
            $dLat = $latMax - $latMin;
            $dLng = $lngMax - $lngMin;

            if ($d === 0) {
                $latMin += (1.0 / 3.0) * $dLat;
                $latMax = $latMin + (1.0 / 3.0) * $dLat;
                $lngMin += (1.0 / 3.0) * $dLng;
                $lngMax = $lngMin + (1.0 / 3.0) * $dLng;
            } elseif ($d === 1) {
                $latMax = $latMin + (1.0 / 3.0) * $dLat;
                $lngMax = $lngMin + 0.5 * $dLng;
            } elseif ($d === 2) {
                $latMax = $latMin + (1.0 / 3.0) * $dLat;
                $lngMin += 0.5 * $dLng;
            } elseif ($d === 3) {
                $latMin += (2.0 / 3.0) * $dLat;
                $lngMax = $lngMin + 0.5 * $dLng;
            } elseif ($d === 4) {
                $latMin += (2.0 / 3.0) * $dLat;
                $lngMin += 0.5 * $dLng;
            } elseif ($d === 5) {
                $latMin += (1.0 / 3.0) * $dLat;
                $latMax = $latMin + (1.0 / 3.0) * $dLat;
                $lngMax = $lngMin + (1.0 / 3.0) * $dLng;
            } elseif ($d === 6) {
                $latMin += (1.0 / 3.0) * $dLat;
                $latMax = $latMin + (1.0 / 3.0) * $dLat;
                $lngMin += (2.0 / 3.0) * $dLng;
            }
        }

        $centerLat = ($latMin + $latMax) / 2.0;
        $centerLng = ($lngMin + $lngMax) / 2.0;

        return new Point($centerLat, $centerLng);
    }

    /**
     * Generate the 6 boundary vertices of the hexagonal cell as a Polygon.
     */
    public static function h3ToGeoBoundary(string $h3Index): Polygon
    {
        $center = self::h3ToGeo($h3Index);
        $resolution = self::h3GetResolution($h3Index);
        $radiusMeters = (self::RES_EDGE_LENGTHS[$resolution] ?? 500.0);

        // Compute 6 vertices of hexagon around centroid
        $vertices = [];
        $earthRadius = 6371000.0;

        for ($i = 0; $i < 6; $i++) {
            $angleDeg = 60.0 * $i + 30.0;
            $angleRad = deg2rad($angleDeg);

            $dLat = ($radiusMeters * sin($angleRad)) / $earthRadius;
            $dLng = ($radiusMeters * cos($angleRad)) / ($earthRadius * cos(deg2rad($center->getLat())));

            $lat = $center->getLat() + rad2deg($dLat);
            $lng = $center->getLng() + rad2deg($dLng);

            $vertices[] = new Point(max(-90.0, min(90.0, $lat)), fmod($lng + 540.0, 360.0) - 180.0);
        }

        // Close ring
        $vertices[] = new Point($vertices[0]->getLat(), $vertices[0]->getLng());

        return new Polygon($vertices);
    }

    /**
     * Extract the resolution level (0 to 15) from an H3 index.
     */
    public static function h3GetResolution(string $h3Index): int
    {
        $parsed = self::parseH3(trim(strtolower($h3Index)));
        return $parsed['resolution'];
    }

    /**
     * Return concentric hexagonal neighbor cells up to ring distance $k.
     *
     * @return array<int, string>
     */
    public static function kRing(string $originH3, int $k = 1): array
    {
        if ($k < 0) {
            return [];
        }

        $center = self::h3ToGeo($originH3);
        $res = self::h3GetResolution($originH3);
        $stepLat = (180.0 / 11.0) / (3.0 ** $res);
        $stepLng = (360.0 / 11.0) / (2.5 ** $res);

        $cells = [$originH3 => true];

        for ($ring = 1; $ring <= $k; $ring++) {
            $numSteps = 6 * $ring;
            for ($i = 0; $i < $numSteps; $i++) {
                $angle = deg2rad(($i * 360.0) / $numSteps);
                $neighborLat = $center->getLat() + ($stepLat * 1.4 * $ring * sin($angle));
                $neighborLng = $center->getLng() + ($stepLng * 1.4 * $ring * cos($angle));

                $neighborH3 = self::geoToH3($neighborLat, $neighborLng, $res);
                $cells[$neighborH3] = true;
            }
        }

        return array_keys($cells);
    }

    /**
     * Validate an H3 hexadecimal index string.
     */
    public static function h3IsValid(string $h3Index): bool
    {
        $hex = trim(strtolower($h3Index));
        if (strlen($hex) !== 15 && strlen($hex) !== 16) {
            return false;
        }

        if (!ctype_xdigit($hex)) {
            return false;
        }

        try {
            $parsed = self::parseH3($hex);
            return $parsed['mode'] === self::H3_CELL_MODE &&
                   $parsed['resolution'] <= self::MAX_RESOLUTION &&
                   $parsed['baseCell'] < self::NUM_BASE_CELLS;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Compute approximate grid distance in hexagonal cell steps between two H3 indices.
     */
    public static function h3Distance(string $origin, string $destination): int
    {
        $p1 = self::h3ToGeo($origin);
        $p2 = self::h3ToGeo($destination);
        $res = self::h3GetResolution($origin);
        $edgeLen = self::RES_EDGE_LENGTHS[$res] ?? 500.0;
        $centerDist = $edgeLen * sqrt(3.0);

        // Great circle distance divided by inter-cell distance
        $meters = DistanceCalculator::haversine($p1, $p2);
        return (int) round($meters / max(1.0, $centerDist));
    }

    /**
     * @return array{mode: int, resolution: int, baseCell: int, digits: array<int>}
     */
    protected static function parseH3(string $hex): array
    {
        $cleanHex = str_pad($hex, 16, '0', STR_PAD_LEFT);
        $bin = pack('H*', $cleanHex);
        $h3 = unpack('J', $bin)[1];

        $mode = ($h3 >> 59) & 0x0F;
        $res = ($h3 >> 52) & 0x0F;
        $baseCell = ($h3 >> 45) & 0x7F;

        $digits = [];
        for ($r = 1; $r <= self::MAX_RESOLUTION; $r++) {
            $bitOffset = 45 - ($r * 3);
            $digits[] = ($h3 >> $bitOffset) & 0x07;
        }

        return [
            'mode' => $mode,
            'resolution' => $res,
            'baseCell' => $baseCell,
            'digits' => $digits,
        ];
    }
}
