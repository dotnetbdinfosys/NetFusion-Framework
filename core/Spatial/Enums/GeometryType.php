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

namespace NetFusion\Core\Spatial\Enums;

enum GeometryType: string
{
    case POINT = 'Point';
    case MULTI_POINT = 'MultiPoint';
    case LINE_STRING = 'LineString';
    case MULTI_LINE_STRING = 'MultiLineString';
    case POLYGON = 'Polygon';
    case MULTI_POLYGON = 'MultiPolygon';
    case BOUNDING_BOX = 'BoundingBox';
}
