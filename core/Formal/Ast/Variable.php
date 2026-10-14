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
 * Variable AST node.
 */
class Variable extends Term
{
    private string $name;

    public function __construct(string $name, Sort $sort)
    {
        parent::__construct(TermKind::VARIABLE, $sort);
        $this->name = $name;
    }

    public static function bool(string $name): self
    {
        return new self($name, Sort::bool());
    }

    public static function int(string $name): self
    {
        return new self($name, Sort::int());
    }

    public static function real(string $name): self
    {
        return new self($name, Sort::real());
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function toSmtLib(): string
    {
        return $this->name;
    }

    public function getFreeVariables(): array
    {
        return [$this->name => $this];
    }
}
