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

namespace NetFusion\Core\IMDG\Exceptions;

class PartitionNotFoundException extends GridException
{
    public function __construct(int $partitionId)
    {
        parent::__construct("Partition [{$partitionId}] not found in grid topology.");
    }
}
