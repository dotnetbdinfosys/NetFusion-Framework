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

use NetFusion\Core\Spatial\Enums\GeometryType;
use NetFusion\Core\Spatial\Exceptions\InvalidCoordinateException;

/**
 * WGS84 Spatial Geographic Point (Latitude, Longitude, Altitude).
 */
class Point
{
    protected float $latitude;
    protected float $longitude;
    protected ?float $altitude;

    public function __construct(float $latitude, float $longitude, ?float $altitude = null)
    {
        if ($latitude < -90.0 || $latitude > 90.0) {
            throw new InvalidCoordinateException(sprintf("Latitude must be between -90 and 90 degrees. Got %f", $latitude));
        }

        if ($longitude < -180.0 || $longitude > 180.0) {
            throw new InvalidCoordinateException(sprintf("Longitude must be between -180 and 180 degrees. Got %f", $longitude));
        }

        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->altitude = $altitude;
    }

    public function getLatitude(): float
    {
        return $this->latitude;
    }

    public function getLat(): float
    {
        return $this->latitude;
    }

    public function getLongitude(): float
    {
        return $this->longitude;
    }

    public function getLng(): float
    {
        return $this->longitude;
    }

    public function getAltitude(): ?float
    {
        return $this->altitude;
    }

    public function getType(): GeometryType
    {
        return GeometryType::POINT;
    }

    /**
     * GeoJSON coordinate tuple: [longitude, latitude] or [longitude, latitude, altitude].
     *
     * @return array<int, float>
     */
    public function getCoordinates(): array
    {
        if ($this->altitude !== null) {
            return [$this->longitude, $this->latitude, $this->altitude];
        }

        return [$this->longitude, $this->latitude];
    }

    /**
     * @return array{type: string, coordinates: array<int, float>}
     */
    public function toGeoJson(): array
    {
        return [
            'type' => GeometryType::POINT->value,
            'coordinates' => $this->getCoordinates(),
        ];
    }

    /**
     * Well-Known Text (WKT) representation: POINT(lng lat)
     */
    public function toWkt(): string
    {
        if ($this->altitude !== null) {
            return sprintf("POINT(%f %f %f)", $this->longitude, $this->latitude, $this->altitude);
        }

        return sprintf("POINT(%f %f)", $this->longitude, $this->latitude);
    }

    /**
     * @return array{lat: float, lng: float, alt: float|null}
     */
    public function toArray(): array
    {
        return [
            'lat' => $this->latitude,
            'lng' => $this->longitude,
            'alt' => $this->altitude,
        ];
    }
}
