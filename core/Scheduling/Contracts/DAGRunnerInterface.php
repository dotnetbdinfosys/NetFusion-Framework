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

namespace NetFusion\Core\Scheduling\Contracts;

use NetFusion\Core\Scheduling\Models\DAGGraph;

interface DAGRunnerInterface
{
    public function run(DAGGraph $dag, array $initialContext = []): array;
}
