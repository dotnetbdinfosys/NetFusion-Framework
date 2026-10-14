<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | Author    : Md. Atiquzzaman Ashish
 | Company   : DotNetBD
 | Website   : www.dotnetbd.com
 | Copyright : © DotNetBD. All rights reserved.
 | License   : MIT
 | Version   : 1.0.0
 |
 |--------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace NetFusion\Core\Workflow\DMN;

/**
 * Class DecisionTable
 *
 * DMN 1.3 Decision Table implementation.
 *
 * A decision table consists of:
 *   - Input columns (conditions)
 *   - Output columns (results)
 *   - Rules (rows) each with conditions and outputs
 *
 * Hit policies:
 *   - UNIQUE  : Exactly one rule may match (throws if multiple match)
 *   - FIRST   : First matching rule wins
 *   - COLLECT : All matching rules collected
 *   - RULE_ORDER: Matching rules in table order (array output)
 *
 * Each condition can be:
 *   - A scalar value (equality check)
 *   - A callable: fn($inputValue) => bool
 *   - null (wildcard — always matches)
 */
class DecisionTable
{
    /** @var string[] Input column names */
    private array $inputColumns;

    /** @var string[] Output column names */
    private array $outputColumns;

    /** @var array[] Rules: [conditions: [], outputs: []] */
    private array $rules = [];

    public function __construct(
        array  $inputColumns,
        array  $outputColumns,
        private string $hitPolicy = 'UNIQUE',
        private string $name      = 'decision'
    ) {
        $this->inputColumns  = $inputColumns;
        $this->outputColumns = $outputColumns;

        if (!in_array(strtoupper($hitPolicy), ['UNIQUE', 'FIRST', 'COLLECT', 'RULE_ORDER'], true)) {
            throw new \InvalidArgumentException("Unknown hit policy: {$hitPolicy}");
        }
        $this->hitPolicy = strtoupper($hitPolicy);
    }

    // ------------------------------------------------------------------
    // Rule management
    // ------------------------------------------------------------------

    /**
     * Add a rule to the table.
     *
     * @param array $conditions Indexed by input column name; null = wildcard
     * @param array $outputs    Indexed by output column name
     * @param string $annotation Optional human-readable note
     */
    public function addRule(array $conditions, array $outputs, string $annotation = ''): self
    {
        $this->rules[] = [
            'conditions'  => $conditions,
            'outputs'     => $outputs,
            'annotation'  => $annotation,
        ];
        return $this;
    }

    // ------------------------------------------------------------------
    // Evaluation
    // ------------------------------------------------------------------

    /**
     * Evaluate the decision table against an input context.
     *
     * @param  array $context Key-value pairs for input columns
     * @return array|null Depends on hit policy:
     *                    UNIQUE/FIRST → single output array or null
     *                    COLLECT/RULE_ORDER → array of output arrays
     */
    public function evaluate(array $context): array|null
    {
        $matched = [];

        foreach ($this->rules as $index => $rule) {
            if ($this->matchesRule($rule['conditions'], $context)) {
                $matched[] = ['index' => $index, 'outputs' => $rule['outputs']];
            }
        }

        return match ($this->hitPolicy) {
            'UNIQUE' => $this->applyUnique($matched),
            'FIRST'  => $matched ? $matched[0]['outputs'] : null,
            'COLLECT', 'RULE_ORDER' => array_column($matched, 'outputs'),
        };
    }

    // ------------------------------------------------------------------
    // Internal
    // ------------------------------------------------------------------

    private function matchesRule(array $conditions, array $context): bool
    {
        foreach ($conditions as $column => $condition) {
            if (!array_key_exists($column, $context)) continue;

            $inputValue = $context[$column];

            if ($condition === null) {
                continue; // Wildcard
            } elseif (is_callable($condition)) {
                if (!$condition($inputValue)) return false;
            } else {
                if ($inputValue != $condition) return false;
            }
        }
        return true;
    }

    private function applyUnique(array $matched): ?array
    {
        if (empty($matched)) return null;
        if (count($matched) > 1) {
            throw new \LogicException(
                "UNIQUE hit policy violated: {$count} rules matched in table '{$this->name}'"
            );
        }
        return $matched[0]['outputs'];
    }

    // ------------------------------------------------------------------
    // Accessors
    // ------------------------------------------------------------------

    public function getName(): string      { return $this->name; }
    public function getHitPolicy(): string { return $this->hitPolicy; }
    public function getRules(): array      { return $this->rules; }
    public function getRuleCount(): int    { return count($this->rules); }
    public function getInputColumns(): array  { return $this->inputColumns; }
    public function getOutputColumns(): array { return $this->outputColumns; }
}
