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

class EdgeNotFoundException extends GraphException
{
    public function __construct(string $edgeId)
    {
        parent::__construct("Graph edge with ID '{$edgeId}' was not found.");
    }
}
