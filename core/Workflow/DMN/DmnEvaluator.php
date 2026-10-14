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
 * Class DmnEvaluator
 *
 * High-level evaluator that looks up a named DecisionTable
 * and evaluates it against a context.
 *
 * Usage:
 *   $evaluator = new DmnEvaluator();
 *   $evaluator->register('loan_approval', $table);
 *   $result = $evaluator->decide('loan_approval', ['credit_score' => 750, 'amount' => 25000]);
 */
class DmnEvaluator
{
    /** @var array<string, DecisionTable> */
    private array $tables = [];

    /** @var array[] Decision audit log */
    private array $auditLog = [];

    // ------------------------------------------------------------------
    // Registration
    // ------------------------------------------------------------------

    public function register(string $name, DecisionTable $table): self
    {
        $this->tables[$name] = $table;
        return $this;
    }

    public function has(string $name): bool
    {
        return isset($this->tables[$name]);
    }

    public function getTable(string $name): ?DecisionTable
    {
        return $this->tables[$name] ?? null;
    }

    // ------------------------------------------------------------------
    // Evaluation
    // ------------------------------------------------------------------

    /**
     * Evaluate a named decision table.
     *
     * @param  string $name    Registered decision table name
     * @param  array  $context Input context key-value pairs
     * @return array|null      Output values (or null if no rule matched)
     * @throws \InvalidArgumentException If decision table not found
     */
    public function decide(string $name, array $context): array|null
    {
        if (!isset($this->tables[$name])) {
            throw new \InvalidArgumentException("Decision table not found: {$name}");
        }

        $table  = $this->tables[$name];
        $result = $table->evaluate($context);

        $this->auditLog[] = [
            'table'      => $name,
            'context'    => $context,
            'result'     => $result,
            'decided_at' => date('Y-m-d H:i:s'),
        ];

        return $result;
    }

    /**
     * Evaluate multiple decision tables as a chain.
     * Each table's output is merged into the context for subsequent tables.
     *
     * @param  string[] $tableNames Ordered list of table names
     * @param  array    $context    Initial context
     * @return array    Final merged context
     */
    public function chain(array $tableNames, array $context): array
    {
        foreach ($tableNames as $name) {
            $result = $this->decide($name, $context);
            if (is_array($result)) {
                $context = array_merge($context, $result);
            }
        }
        return $context;
    }

    // ------------------------------------------------------------------
    // Audit
    // ------------------------------------------------------------------

    public function getAuditLog(): array  { return $this->auditLog; }
    public function clearAudit(): void   { $this->auditLog = []; }
    public function getTableNames(): array { return array_keys($this->tables); }
}
