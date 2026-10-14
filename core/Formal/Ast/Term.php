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
 * Abstract First-Order Logic AST Term node.
 */
abstract class Term
{
    protected TermKind $kind;
    protected Sort $sort;

    public function __construct(TermKind $kind, Sort $sort)
    {
        $this->kind = $kind;
        $this->sort = $sort;
    }

    public function getKind(): TermKind
    {
        return $this->kind;
    }

    public function getSort(): Sort
    {
        return $this->sort;
    }

    abstract public function toSmtLib(): string;

    abstract public function getFreeVariables(): array;

    public function __toString(): string
    {
        return $this->toSmtLib();
    }
}
