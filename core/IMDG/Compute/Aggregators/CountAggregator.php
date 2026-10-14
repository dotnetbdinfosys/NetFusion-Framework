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

class CountAggregator
{
    public static function aggregate(DistributedMapInterface $map, ?callable $predicate = null): int
    {
        if ($predicate === null) {
            return $map->size();
        }

        $count = 0;
        foreach ($map->values() as $item) {
            if ($predicate($item)) {
                $count++;
            }
        }
        return $count;
    }
}
