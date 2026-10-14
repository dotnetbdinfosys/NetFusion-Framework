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

namespace NetFusion\Core\Search\Exceptions;

class IndexNotFoundException extends SearchException
{
    public function __construct(string $indexName)
    {
        parent::__construct("Search index [{$indexName}] does not exist.");
    }
}
