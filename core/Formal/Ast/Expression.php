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

namespace NetFusion\Core\Formal\Ast;

use NetFusion\Core\Formal\Enums\TermKind;

/**
 * Functional Operation or Predicate Expression AST node.
 */
class Expression extends Term
{
    private string $operator;
    /** @var Term[] */
    private array $arguments;

    public function __construct(string $operator, array $arguments, Sort $sort)
    {
        parent::__construct(TermKind::OPERATION, $sort);
        $this->operator  = $operator;
        $this->arguments = $arguments;
    }

    public static function not(Term $arg): self
    {
        return new self('not', [$arg], Sort::bool());
    }

    public static function and(Term ...$args): self
    {
        return new self('and', $args, Sort::bool());
    }

    public static function or(Term ...$args): self
    {
        return new self('or', $args, Sort::bool());
    }

    public static function implies(Term $a, Term $b): self
    {
        return new self('=>', [$a, $b], Sort::bool());
    }

    public static function eq(Term $a, Term $b): self
    {
        return new self('=', [$a, $b], Sort::bool());
    }

    public static function distinct(Term ...$args): self
    {
        return new self('distinct', $args, Sort::bool());
    }

    public static function lt(Term $a, Term $b): self
    {
        return new self('<', [$a, $b], Sort::bool());
    }

    public static function lte(Term $a, Term $b): self
    {
        return new self('<=', [$a, $b], Sort::bool());
    }

    public static function gt(Term $a, Term $b): self
    {
        return new self('>', [$a, $b], Sort::bool());
    }

    public static function gte(Term $a, Term $b): self
    {
        return new self('>=', [$a, $b], Sort::bool());
    }

    public static function add(Term ...$args): self
    {
        $sort = !empty($args) ? $args[0]->getSort() : Sort::int();
        return new self('+', $args, $sort);
    }

    public static function sub(Term $a, Term $b): self
    {
        return new self('-', [$a, $b], $a->getSort());
    }

    public static function mul(Term ...$args): self
    {
        $sort = !empty($args) ? $args[0]->getSort() : Sort::int();
        return new self('*', $args, $sort);
    }

    public static function div(Term $a, Term $b): self
    {
        return new self('/', [$a, $b], $a->getSort());
    }

    public function getOperator(): string
    {
        return $this->operator;
    }

    /**
     * @return Term[]
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function toSmtLib(): string
    {
        if (empty($this->arguments)) {
            return "({$this->operator})";
        }
        $argsStr = implode(' ', array_map(fn(Term $t) => $t->toSmtLib(), $this->arguments));
        return "({$this->operator} {$argsStr})";
    }

    public function getFreeVariables(): array
    {
        $vars = [];
        foreach ($this->arguments as $arg) {
            $vars = array_merge($vars, $arg->getFreeVariables());
        }
        return $vars;
    }
}
