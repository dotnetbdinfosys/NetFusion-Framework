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

class OutputClause implements JsonSerializable
{
    protected string $name;
    protected ?string $label;
    protected string $typeRef;
    protected mixed $defaultValue;
    /** @var array<mixed>|null Priority order for PRIORITY / OUTPUT_ORDER hit policies */
    protected ?array $priorityValues;

    public function __construct(
        string $name,
        ?string $label = null,
        string $typeRef = 'string',
        mixed $defaultValue = null,
        ?array $priorityValues = null
    ) {
        $this->name = trim($name);
        $this->label = $label ?? $this->name;
        $this->typeRef = strtolower($typeRef);
        $this->defaultValue = $defaultValue;
        $this->priorityValues = $priorityValues;
    }

    public static function make(
        string $name,
        ?string $label = null,
        string $typeRef = 'string',
        mixed $defaultValue = null
    ): self {
        return new self($name, $label, $typeRef, $defaultValue);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label ?? $this->name;
    }

    public function getTypeRef(): string
    {
        return $this->typeRef;
    }

    public function getDefaultValue(): mixed
    {
        return $this->defaultValue;
    }

    /**
     * @return array<mixed>|null
     */
    public function getPriorityValues(): ?array
    {
        return $this->priorityValues;
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'typeRef' => $this->typeRef,
            'defaultValue' => $this->defaultValue,
            'priorityValues' => $this->priorityValues,
        ];
    }
}
