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

namespace NetFusion\Core\Graph\Cypher;

class NodePattern
{
    public function __construct(
        public readonly ?string $variable = null,
        public readonly ?string $label = null,
        public readonly array $properties = []
    ) {
    }
}

class EdgePattern
{
    public function __construct(
        public readonly ?string $variable = null,
        public readonly ?string $type = null,
        public readonly string $direction = '->', // '->', '<-', '--'
        public readonly int $minHops = 1,
        public readonly ?int $maxHops = 1,
        public readonly array $properties = []
    ) {
    }
}

class PathPattern
{
    /**
     * @param array<NodePattern|EdgePattern> $elements Alternating NodePattern and EdgePattern (Node, Edge, Node, Edge, Node...)
     */
    public function __construct(
        public readonly array $elements = []
    ) {
    }
}

class ReturnItem
{
    public function __construct(
        public readonly string $expression,
        public readonly ?string $variable = null,
        public readonly ?string $property = null,
        public readonly ?string $aggregation = null, // 'COUNT', 'SUM', 'AVG', 'MIN', 'MAX', 'COLLECT'
        public readonly ?string $alias = null
    ) {
    }
}

class OrderByItem
{
    public function __construct(
        public readonly string $expression,
        public readonly string $direction = 'ASC' // 'ASC' or 'DESC'
    ) {
    }
}

class CypherAST
{
    /**
     * @param array<PathPattern> $matchPatterns
     * @param array|null $whereExpression
     * @param array<PathPattern> $createPatterns
     * @param array<array{variable: string, property?: string, label?: string, value?: mixed}> $setClauses
     * @param array<string> $deleteVariables
     * @param bool $detachDelete
     * @param array<ReturnItem> $returnItems
     * @param bool $distinct
     * @param array<OrderByItem> $orderByItems
     * @param int|null $skip
     * @param int|null $limit
     */
    public function __construct(
        public array $matchPatterns = [],
        public ?array $whereExpression = null,
        public array $createPatterns = [],
        public array $setClauses = [],
        public array $deleteVariables = [],
        public bool $detachDelete = false,
        public array $returnItems = [],
        public bool $distinct = false,
        public array $orderByItems = [],
        public ?int $skip = null,
        public ?int $limit = null
    ) {
    }
}
