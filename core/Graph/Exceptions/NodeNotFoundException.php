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

namespace NetFusion\Core\Graph\Exceptions;

class NodeNotFoundException extends GraphException
{
    public function __construct(string $nodeId)
    {
        parent::__construct("Graph node with ID '{$nodeId}' was not found.");
    }
}
