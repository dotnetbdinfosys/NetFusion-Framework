<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\IMDG\Compute\Aggregators;

use NetFusion\Core\IMDG\Contracts\DistributedMapInterface;

class AverageAggregator
{
    public static function aggregate(DistributedMapInterface $map, ?string $property = null): float
    {
        $sum = 0.0;
        $count = 0;

        foreach ($map->values() as $item) {
            if ($property !== null) {
                if (is_array($item) && isset($item[$property])) {
                    $sum += (float) $item[$property];
                    $count++;
                } elseif (is_object($item) && isset($item->{$property})) {
                    $sum += (float) $item->{$property};
                    $count++;
                }
            } elseif (is_numeric($item)) {
                $sum += (float) $item;
                $count++;
            }
        }

        return $count > 0 ? ($sum / $count) : 0.0;
    }
}
