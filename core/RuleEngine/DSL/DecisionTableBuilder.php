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

namespace NetFusion\Core\RuleEngine\DSL;

use NetFusion\Core\RuleEngine\DMN\DecisionRule;
use NetFusion\Core\RuleEngine\DMN\DecisionTable;
use NetFusion\Core\RuleEngine\DMN\HitPolicy;
use NetFusion\Core\RuleEngine\DMN\InputClause;
use NetFusion\Core\RuleEngine\DMN\OutputClause;

class DecisionTableBuilder
{
    protected string $name;
    protected string $hitPolicy = HitPolicy::FIRST;
    /** @var array<InputClause> */
    protected array $inputs = [];
    /** @var array<OutputClause> */
    protected array $outputs = [];
    /** @var array<DecisionRule> */
    protected array $rules = [];

    public function __construct(string $name)
    {
        $this->name = trim($name);
    }

    public static function name(string $name): self
    {
        return new self($name);
    }

    public function hitPolicy(string $hitPolicy): self
    {
        $this->hitPolicy = strtoupper($hitPolicy);
        return $this;
    }

    public function input(
        string $expression,
        ?string $label = null,
        string $typeRef = 'string',
        ?array $allowedValues = null
    ): self {
        $this->inputs[] = new InputClause($expression, $label, $typeRef, $allowedValues);
        return $this;
    }

    public function output(
        string $name,
        ?string $label = null,
        string $typeRef = 'string',
        mixed $defaultValue = null,
        ?array $priorityValues = null
    ): self {
        $this->outputs[] = new OutputClause($name, $label, $typeRef, $defaultValue, $priorityValues);
        return $this;
    }

    /**
     * @param array<string> $inputEntries FEEL expressions
     * @param array<mixed> $outputEntries
     * @param string|null $description
     */
    public function rule(array $inputEntries, array $outputEntries, ?string $description = null): self
    {
        $this->rules[] = new DecisionRule($inputEntries, $outputEntries, $description);
        return $this;
    }

    public function build(): DecisionTable
    {
        $table = new DecisionTable($this->name, $this->hitPolicy, $this->inputs, $this->outputs);
        foreach ($this->rules as $rule) {
            $table->addRule($rule);
        }
        return $table;
    }
}
