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
 * RFC 7946 Standard GeoJSON Parser & Serializer.
 */
class GeoJson
{
    /**
     * Parse GeoJSON string or decoded array into spatial geometry objects.
     */
    public static function parse(string|array $input): mixed
    {
        $data = is_string($input) ? json_decode($input, true) : $input;
        if (!is_array($data) || !isset($data['type'])) {
            throw new InvalidArgumentException("Invalid GeoJSON structure: missing 'type' key.");
        }

        $type = $data['type'];

        if ($type === 'Feature') {
            return self::parse($data['geometry'] ?? []);
        }

        if ($type === 'FeatureCollection') {
            $features = [];
            foreach ($data['features'] ?? [] as $f) {
                $features[] = self::parse($f);
            }
            return $features;
        }

        $coords = $data['coordinates'] ?? [];

        return match ($type) {
            'Point' => self::coordsToPoint($coords),
            'LineString' => new LineString(array_map([self::class, 'coordsToPoint'], $coords)),
            'Polygon' => self::coordsToPolygon($coords),
            default => throw new InvalidArgumentException("Unsupported GeoJSON geometry type: {$type}"),
        };
    }

    /**
     * @param array<int, float> $c [lng, lat, ?alt]
     */
    protected static function coordsToPoint(array $c): Point
    {
        if (count($c) < 2) {
            throw new InvalidArgumentException("Point coordinates must contain at least [lng, lat].");
        }
        $alt = count($c) >= 3 ? (float) $c[2] : null;
        return new Point((float) $c[1], (float) $c[0], $alt);
    }

    /**
     * @param array<int, array<int, array<int, float>>> $rings
     */
    protected static function coordsToPolygon(array $rings): Polygon
    {
        if (empty($rings)) {
            throw new InvalidArgumentException("Polygon requires at least one linear ring.");
        }

        $exterior = array_map([self::class, 'coordsToPoint'], $rings[0]);
        $holes = [];

        for ($i = 1; $i < count($rings); $i++) {
            $holes[] = array_map([self::class, 'coordsToPoint'], $rings[$i]);
        }

        return new Polygon($exterior, $holes);
    }

    /**
     * Create a standard GeoJSON Feature object.
     *
     * @param array<string, mixed> $properties
     * @return array<string, mixed>
     */
    public static function feature(Point|LineString|Polygon $geometry, array $properties = [], ?string $id = null): array
    {
        $feature = [
            'type' => 'Feature',
            'geometry' => $geometry->toGeoJson(),
            'properties' => $properties,
        ];

        if ($id !== null) {
            $feature['id'] = $id;
        }

        return $feature;
    }

    /**
     * Create a standard GeoJSON FeatureCollection.
     *
     * @param array<int, array<string, mixed>> $features
     * @return array{type: string, features: array<int, array<string, mixed>>, bbox?: array<int, float>}
     */
    public static function featureCollection(array $features, ?BoundingBox $bbox = null): array
    {
        $fc = [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];

        if ($bbox !== null) {
            $fc['bbox'] = $bbox->toGeoJson();
        }

        return $fc;
    }

    /**
     * Serialize any geometry or feature to JSON string.
     */
    public static function encode(mixed $spatial, int $flags = JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION): string
    {
        if (is_object($spatial) && method_exists($spatial, 'toGeoJson')) {
            $data = $spatial->toGeoJson();
        } else {
            $data = $spatial;
        }

        return (string) json_encode($data, $flags);
    }
}
