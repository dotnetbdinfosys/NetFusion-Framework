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

namespace NetFusion\Core\Formal\Symbolic;

use NetFusion\Core\Formal\Ast\Expression;
use NetFusion\Core\Formal\Ast\Term;

/**
 * Symbolic Execution Program State.
 * Tracks variable mappings (memory store) and path condition.
 */
class SymbolicState
{
    /** @var array<string, SymbolicValue> */
    private array $variables = [];
    private PathCondition $pathCondition;

    public function __construct(array $variables = [], ?PathCondition $pc = null)
    {
        $this->variables     = $variables;
        $this->pathCondition = $pc ?? new PathCondition();
    }

    public function get(string $name): ?SymbolicValue
    {
        return $this->variables[$name] ?? null;
    }

    public function set(string $name, SymbolicValue $val): void
    {
        $this->variables[$name] = $val;
    }

    public function getPathCondition(): PathCondition
    {
        return $this->pathCondition;
    }

    /**
     * Branch into two forked states along condition (Then and Else).
     *
     * @return array{then: SymbolicState, else: SymbolicState}
     */
    public function branch(Term $condition): array
    {
        $thenState = new SymbolicState(
            $this->variables,
            $this->pathCondition->addConstraint($condition)
        );

        $elseState = new SymbolicState(
            $this->variables,
            $this->pathCondition->addConstraint(Expression::not($condition))
        );

        return [
            'then' => $thenState,
            'else' => $elseState,
        ];
    }

    public function toArray(): array
    {
        $vars = [];
        foreach ($this->variables as $k => $v) {
            $vars[$k] = (string)$v;
        }
        return [
            'variables'      => $vars,
            'path_condition' => (string)$this->pathCondition,
        ];
    }
}
