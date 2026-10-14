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

namespace NetFusion\Core\Auth\Policy\Evaluator;

use NetFusion\Core\Auth\Policy\EvaluationContext;

class ConditionEvaluator
{
    /**
     * Evaluate rule conditions against evaluation context.
     *
     * @param mixed $conditions
     * @param EvaluationContext $context
     * @return bool
     */
    public static function evaluate(mixed $conditions, EvaluationContext $context): bool
    {
        if ($conditions === null || $conditions === true) {
            return true;
        }

        if ($conditions === false) {
            return false;
        }

        if (is_callable($conditions)) {
            return (bool)$conditions($context);
        }

        if (!is_array($conditions)) {
            return false;
        }

        foreach ($conditions as $key => $expected) {
            if ($key === '$or' && is_array($expected)) {
                $orMatch = false;
                foreach ($expected as $orCond) {
                    if (self::evaluate($orCond, $context)) {
                        $orMatch = true;
                        break;
                    }
                }
                if (!$orMatch) {
                    return false;
                }
                continue;
            }

            if ($key === '$and' && is_array($expected)) {
                foreach ($expected as $andCond) {
                    if (!self::evaluate($andCond, $context)) {
                        return false;
                    }
                }
                continue;
            }

            if ($key === '$not' && is_array($expected)) {
                if (self::evaluate($expected, $context)) {
                    return false;
                }
                continue;
            }

            // Standard attribute path (e.g. 'subject.department', 'resource.owner_id')
            $actual = $context->get((string)$key);

            if (!self::matchValue($actual, $expected, $context)) {
                return false;
            }
        }

        return true;
    }

    protected static function matchValue(mixed $actual, mixed $expected, EvaluationContext $context): bool
    {
        // If expected is an operator array: ['in' => ['a', 'b']], ['$gte' => 3], ['eq' => '$resource.department']
        if (is_array($expected) && !empty($expected) && is_string(array_key_first($expected))) {
            $firstKey = (string)array_key_first($expected);
            $normalizedOp = strtolower(ltrim($firstKey, '$'));
            $knownOps = ['eq', 'ne', 'neq', 'gt', 'gte', 'lt', 'lte', 'in', 'nin', 'not_in', 'contains', 'starts_with', 'ends_with', 'regex', 'between', '==', '!=', '>', '>=', '<', '<='];

            if (in_array($normalizedOp, $knownOps, true) || str_starts_with($firstKey, '$')) {
                foreach ($expected as $op => $targetVal) {
                    if (!self::evaluateOperator((string)$op, $actual, $targetVal, $context)) {
                        return false;
                    }
                }
                return true;
            }
        }

        // Direct attribute pointer string (e.g. '$resource.owner_id' or 'resource.owner_id')
        if (is_string($expected) && (
            str_starts_with($expected, '$subject.') || str_starts_with($expected, '$resource.') || str_starts_with($expected, '$env.') || str_starts_with($expected, '$environment.') ||
            str_starts_with($expected, 'subject.') || str_starts_with($expected, 'resource.') || str_starts_with($expected, 'env.') || str_starts_with($expected, 'environment.')
        )) {
            $path = ltrim($expected, '$');
            $expectedVal = $context->get($path);
            return $actual == $expectedVal;
        }

        return $actual == $expected;
    }

    protected static function evaluateOperator(string $op, mixed $actual, mixed $target, EvaluationContext $context): bool
    {
        $op = strtolower(ltrim($op, '$'));

        // If target is a context pointer string
        if (is_string($target) && (
            str_starts_with($target, '$subject.') || str_starts_with($target, '$resource.') || str_starts_with($target, '$env.') || str_starts_with($target, '$environment.') ||
            str_starts_with($target, 'subject.') || str_starts_with($target, 'resource.') || str_starts_with($target, 'env.') || str_starts_with($target, 'environment.')
        )) {
            $path = ltrim($target, '$');
            $target = $context->get($path);
        }

        return match ($op) {
            'eq', '==' => $actual == $target,
            'ne', 'neq', '!=' => $actual != $target,
            'gt', '>' => $actual > $target,
            'gte', '>=' => $actual >= $target,
            'lt', '<' => $actual < $target,
            'lte', '<=' => $actual <= $target,
            'in' => is_array($target) && in_array($actual, $target, true),
            'nin', 'not_in' => is_array($target) && !in_array($actual, $target, true),
            'contains' => is_array($actual) ? in_array($target, $actual, true) : (is_string($actual) && str_contains((string)$actual, (string)$target)),
            'starts_with' => is_string($actual) && str_starts_with((string)$actual, (string)$target),
            'ends_with' => is_string($actual) && str_ends_with((string)$actual, (string)$target),
            'regex' => is_string($actual) && (bool)preg_match((string)$target, (string)$actual),
            'between' => is_array($target) && count($target) >= 2 && ($actual >= $target[0] && $actual <= $target[1]),
            default => false,
        };
    }
}
