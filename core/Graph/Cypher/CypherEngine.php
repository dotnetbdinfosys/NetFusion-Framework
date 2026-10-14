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

use NetFusion\Core\Graph\Contracts\QueryEngineInterface;
use NetFusion\Core\Graph\Contracts\GraphStoreInterface;
use NetFusion\Core\Graph\Contracts\NodeInterface;
use NetFusion\Core\Graph\Contracts\EdgeInterface;
use NetFusion\Core\Graph\Models\Node;
use NetFusion\Core\Graph\Models\Edge;
use NetFusion\Core\Graph\Models\QueryResult;

class CypherEngine implements QueryEngineInterface
{
    protected CypherParser $parser;

    public function __construct(
        protected GraphStoreInterface $store
    ) {
        $this->parser = new CypherParser();
    }

    public function query(string $query, array $parameters = []): QueryResult
    {
        $start = microtime(true);
        $ast = $this->parser->parse($query);

        $stats = [
            'nodes_created' => 0,
            'edges_created' => 0,
            'nodes_deleted' => 0,
            'edges_deleted' => 0,
            'properties_set' => 0,
        ];

        // 1. MATCH phase
        $bindings = [[]];
        if (!empty($ast->matchPatterns)) {
            $matcher = new PatternMatcher($this->store);
            $bindings = $matcher->match($ast->matchPatterns);
        }

        // 2. WHERE filter phase
        if ($ast->whereExpression !== null) {
            $bindings = array_values(array_filter($bindings, function (array $binding) use ($ast, $parameters) {
                return $this->evaluateExpression($ast->whereExpression, $binding, $parameters);
            }));
        }

        // 3. CREATE phase
        if (!empty($ast->createPatterns)) {
            $newBindings = [];
            foreach ($bindings as $binding) {
                $b = $binding;
                foreach ($ast->createPatterns as $path) {
                    $elements = $path->elements;
                    $prevNode = null;

                    for ($i = 0; $i < count($elements); $i++) {
                        $elem = $elements[$i];

                        if ($elem instanceof NodePattern) {
                            $node = null;
                            if ($elem->variable !== null && isset($b[$elem->variable]) && $b[$elem->variable] instanceof NodeInterface) {
                                $node = $b[$elem->variable];
                            } else {
                                $id = $elem->properties['id'] ?? ($elem->variable ?? uniqid('node_'));
                                $labels = $elem->label !== null ? [$elem->label] : [];
                                $props = $elem->properties;
                                $node = Node::create((string)$id, $labels, $props);
                                $this->store->addNode($node);
                                $stats['nodes_created']++;

                                if ($elem->variable !== null) {
                                    $b[$elem->variable] = $node;
                                }
                            }
                            $prevNode = $node;
                        } elseif ($elem instanceof EdgePattern && $prevNode !== null && isset($elements[$i + 1]) && $elements[$i + 1] instanceof NodePattern) {
                            $targetElem = $elements[$i + 1];
                            $targetNode = null;

                            if ($targetElem->variable !== null && isset($b[$targetElem->variable]) && $b[$targetElem->variable] instanceof NodeInterface) {
                                $targetNode = $b[$targetElem->variable];
                            } else {
                                $targetId = $targetElem->properties['id'] ?? ($targetElem->variable ?? uniqid('node_'));
                                $targetLabels = $targetElem->label !== null ? [$targetElem->label] : [];
                                $targetProps = $targetElem->properties;
                                $targetNode = Node::create((string)$targetId, $targetLabels, $targetProps);
                                $this->store->addNode($targetNode);
                                $stats['nodes_created']++;

                                if ($targetElem->variable !== null) {
                                    $b[$targetElem->variable] = $targetNode;
                                }
                            }

                            $sourceId = ($elem->direction === '<-') ? $targetNode->getId() : $prevNode->getId();
                            $destId = ($elem->direction === '<-') ? $prevNode->getId() : $targetNode->getId();
                            $edgeId = $elem->properties['id'] ?? ($elem->variable ?? uniqid('edge_'));
                            $type = $elem->type ?? 'RELATED_TO';
                            $directed = ($elem->direction !== '--');
                            $weight = (float)($elem->properties['weight'] ?? 1.0);

                            $edge = Edge::create((string)$edgeId, $sourceId, $destId, $type, $directed, $weight, $elem->properties);
                            $this->store->addEdge($edge);
                            $stats['edges_created']++;

                            if ($elem->variable !== null) {
                                $b[$elem->variable] = $edge;
                            }

                            $prevNode = $targetNode;
                            $i++; // skip target node in loop
                        }
                    }
                }
                $newBindings[] = $b;
            }
            $bindings = $newBindings;
        }

        // 4. SET phase
        if (!empty($ast->setClauses)) {
            foreach ($bindings as $binding) {
                foreach ($ast->setClauses as $set) {
                    $var = $set['variable'];
                    if (isset($binding[$var])) {
                        $entity = $binding[$var];
                        if ($entity instanceof NodeInterface) {
                            if (isset($set['label'])) {
                                $entity->addLabel($set['label']);
                                $this->store->addNode($entity); // update
                                $stats['properties_set']++;
                            } elseif (isset($set['property'])) {
                                $val = $this->resolveValue($set['value'], $binding, $parameters);
                                $entity->setProperty($set['property'], $val);
                                $this->store->addNode($entity);
                                $stats['properties_set']++;
                            }
                        } elseif ($entity instanceof EdgeInterface && isset($set['property'])) {
                            $val = $this->resolveValue($set['value'], $binding, $parameters);
                            $entity->setProperty($set['property'], $val);
                            $this->store->addEdge($entity);
                            $stats['properties_set']++;
                        }
                    }
                }
            }
        }

        // 5. DELETE phase
        if (!empty($ast->deleteVariables)) {
            foreach ($bindings as $binding) {
                foreach ($ast->deleteVariables as $var) {
                    if (isset($binding[$var])) {
                        $entity = $binding[$var];
                        if ($entity instanceof NodeInterface) {
                            $inEdges = $this->store->getInEdges($entity->getId());
                            $outEdges = $this->store->getOutEdges($entity->getId());
                            $stats['edges_deleted'] += count($inEdges) + count($outEdges);

                            if ($this->store->removeNode($entity->getId())) {
                                $stats['nodes_deleted']++;
                            }
                        } elseif ($entity instanceof EdgeInterface) {
                            if ($this->store->removeEdge($entity->getId())) {
                                $stats['edges_deleted']++;
                            }
                        }
                    }
                }
            }
        }

        // 6. RETURN phase
        $records = [];
        $columns = [];

        if (!empty($ast->returnItems)) {
            $hasAggregation = false;
            foreach ($ast->returnItems as $item) {
                if ($item->aggregation !== null) {
                    $hasAggregation = true;
                    break;
                }
            }

            foreach ($ast->returnItems as $item) {
                $colName = $item->alias ?? $item->expression;
                $columns[] = $colName;
            }

            if ($hasAggregation) {
                // Compute single aggregated row or grouped records
                $row = [];
                foreach ($ast->returnItems as $item) {
                    $colName = $item->alias ?? $item->expression;
                    $row[$colName] = $this->computeAggregation($item, $bindings);
                }
                $records[] = $row;
            } else {
                foreach ($bindings as $binding) {
                    $row = [];
                    foreach ($ast->returnItems as $item) {
                        $colName = $item->alias ?? $item->expression;
                        $row[$colName] = $this->evaluateReturnItem($item, $binding);
                    }
                    $records[] = $row;
                }
            }

            if ($ast->distinct) {
                $unique = [];
                $distinctRecords = [];
                foreach ($records as $r) {
                    $k = json_encode($r);
                    if (!isset($unique[$k])) {
                        $unique[$k] = true;
                        $distinctRecords[] = $r;
                    }
                }
                $records = $distinctRecords;
            }

            // ORDER BY
            if (!empty($ast->orderByItems)) {
                usort($records, function (array $a, array $b) use ($ast) {
                    foreach ($ast->orderByItems as $order) {
                        $col = $order->expression;
                        $valA = $a[$col] ?? null;
                        $valB = $b[$col] ?? null;

                        if ($valA === $valB) {
                            continue;
                        }

                        $cmp = ($valA < $valB) ? -1 : 1;
                        return ($order->direction === 'DESC') ? -$cmp : $cmp;
                    }
                    return 0;
                });
            }

            // SKIP & LIMIT
            $skip = $ast->skip ?? 0;
            $limit = $ast->limit ?? count($records);
            $records = array_slice($records, $skip, $limit);
        }

        $elapsedMs = round((microtime(true) - $start) * 1000, 3);
        return new QueryResult($records, $columns, $elapsedMs, $stats);
    }

    protected function evaluateReturnItem(ReturnItem $item, array $binding): mixed
    {
        $var = $item->variable;
        if ($var === null || !isset($binding[$var])) {
            return null;
        }

        $entity = $binding[$var];

        if ($item->property !== null) {
            if ($entity instanceof NodeInterface || $entity instanceof EdgeInterface) {
                return $entity->getProperty($item->property);
            }
            return null;
        }

        if ($entity instanceof NodeInterface || $entity instanceof EdgeInterface) {
            return $entity->toArray();
        }

        return $entity;
    }

    protected function computeAggregation(ReturnItem $item, array $bindings): mixed
    {
        $agg = $item->aggregation;
        $var = $item->variable;
        $prop = $item->property;

        if ($agg === 'COUNT') {
            if ($var === '*' || $prop === null) {
                return count($bindings);
            }
            $nonNull = 0;
            foreach ($bindings as $b) {
                if (isset($b[$var]) && ($b[$var] instanceof NodeInterface || $b[$var] instanceof EdgeInterface)) {
                    if ($b[$var]->getProperty($prop) !== null) {
                        $nonNull++;
                    }
                }
            }
            return $nonNull;
        }

        $values = [];
        foreach ($bindings as $b) {
            if (isset($b[$var]) && ($b[$var] instanceof NodeInterface || $b[$var] instanceof EdgeInterface)) {
                $val = $prop !== null ? $b[$var]->getProperty($prop) : null;
                if (is_numeric($val)) {
                    $values[] = (float)$val;
                } elseif ($agg === 'COLLECT') {
                    $values[] = $prop !== null ? $b[$var]->getProperty($prop) : $b[$var]->toArray();
                }
            }
        }

        return match ($agg) {
            'SUM' => array_sum($values),
            'AVG' => count($values) > 0 ? array_sum($values) / count($values) : 0.0,
            'MIN' => count($values) > 0 ? min($values) : null,
            'MAX' => count($values) > 0 ? max($values) : null,
            'COLLECT' => $values,
            default => null,
        };
    }

    protected function evaluateExpression(array $expr, array $binding, array $params): bool
    {
        $type = $expr['type'] ?? '';

        if ($type === 'logical') {
            $op = $expr['operator'];
            $left = $this->evaluateExpression($expr['left'], $binding, $params);
            $right = $this->evaluateExpression($expr['right'], $binding, $params);

            return match ($op) {
                'AND' => $left && $right,
                'OR' => $left || $right,
                'XOR' => $left xor $right,
                default => false,
            };
        }

        if ($type === 'not') {
            return !$this->evaluateExpression($expr['expression'], $binding, $params);
        }

        if ($type === 'binary') {
            $op = $expr['operator'];
            $leftVal = $this->resolveValue($expr['left'], $binding, $params);
            $rightVal = $this->resolveValue($expr['right'], $binding, $params);

            return match ($op) {
                '=' => $leftVal == $rightVal,
                '!=', '<>' => $leftVal != $rightVal,
                '>' => $leftVal > $rightVal,
                '<' => $leftVal < $rightVal,
                '>=' => $leftVal >= $rightVal,
                '<=' => $leftVal <= $rightVal,
                'CONTAINS' => is_string($leftVal) && is_string($rightVal) && str_contains($leftVal, $rightVal),
                'STARTS WITH' => is_string($leftVal) && is_string($rightVal) && str_starts_with($leftVal, $rightVal),
                'ENDS WITH' => is_string($leftVal) && is_string($rightVal) && str_ends_with($leftVal, $rightVal),
                'IN' => is_array($rightVal) && in_array($leftVal, $rightVal, true),
                default => false,
            };
        }

        return (bool)$this->resolveValue($expr, $binding, $params);
    }

    protected function resolveValue(mixed $expr, array $binding, array $params): mixed
    {
        if (!is_array($expr) || !isset($expr['type'])) {
            return $expr;
        }

        $type = $expr['type'];

        if ($type === 'literal') {
            return $expr['value'];
        }

        if ($type === 'variable') {
            $var = $expr['variable'];
            if (str_starts_with($var, '$')) {
                $paramKey = substr($var, 1);
                return $params[$paramKey] ?? null;
            }
            return $binding[$var] ?? null;
        }

        if ($type === 'property_access') {
            $var = $expr['variable'];
            $prop = $expr['property'];
            if (isset($binding[$var])) {
                $entity = $binding[$var];
                if ($entity instanceof NodeInterface || $entity instanceof EdgeInterface) {
                    return $entity->getProperty($prop);
                }
            }
            return null;
        }

        if ($type === 'list') {
            return array_map(fn($el) => $this->resolveValue($el, $binding, $params), $expr['elements']);
        }

        return null;
    }
}
