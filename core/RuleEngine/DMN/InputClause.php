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

namespace NetFusion\Core\RuleEngine\DMN;

use JsonSerializable;

class InputClause implements JsonSerializable
{
    protected string $expression;
    protected ?string $label;
    protected string $typeRef;
    /** @var array<string>|null Allowed values */
    protected ?array $allowedValues;

    public function __construct(
        string $expression,
        ?string $label = null,
        string $typeRef = 'string',
        ?array $allowedValues = null
    ) {
        $this->expression = trim($expression);
        $this->label = $label ?? $this->expression;
        $this->typeRef = strtolower($typeRef);
        $this->allowedValues = $allowedValues;
    }

    public static function make(string $expression, ?string $label = null, string $typeRef = 'string'): self
    {
        return new self($expression, $label, $typeRef);
    }

    public function getExpression(): string
    {
        return $this->expression;
    }

    public function getLabel(): string
    {
        return $this->label ?? $this->expression;
    }

    public function getTypeRef(): string
    {
        return $this->typeRef;
    }

    public function getAllowedValues(): ?array
    {
        return $this->allowedValues;
    }

    public function jsonSerialize(): array
    {
        return [
            'expression' => $this->expression,
            'label' => $this->label,
            'typeRef' => $this->typeRef,
            'allowedValues' => $this->allowedValues,
        ];
    }
}
