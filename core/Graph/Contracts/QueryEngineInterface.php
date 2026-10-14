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

namespace NetFusion\Core\Graph\Contracts;

use NetFusion\Core\Graph\Models\QueryResult;

interface QueryEngineInterface
{
    /**
     * Execute a Cypher query string against the graph.
     *
     * @param string $query
     * @param array<string, mixed> $parameters
     * @return QueryResult
     */
    public function query(string $query, array $parameters = []): QueryResult;
}
