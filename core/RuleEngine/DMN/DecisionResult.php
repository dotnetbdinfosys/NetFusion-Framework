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

use ArrayAccess;
use JsonSerializable;

/**
 * @implements ArrayAccess<string, mixed>
 */
class DecisionResult implements JsonSerializable, ArrayAccess
{
    protected string $tableName;
    protected string $hitPolicy;
    /** @var array<string, mixed>|mixed Single map or array of maps/values */
    protected mixed $outputs;
    /** @var array<int> Indices of rules that matched */
    protected array $matchedRuleIndices;
    /** @var array<DecisionRule> */
    protected array $matchedRules;
    protected float $evaluationTimeMs;

    public function __construct(
        string $tableName,
        string $hitPolicy,
        mixed $outputs,
        array $matchedRuleIndices = [],
        array $matchedRules = [],
        float $evaluationTimeMs = 0.0
    ) {
        $this->tableName = $tableName;
        $this->hitPolicy = $hitPolicy;
        $this->outputs = $outputs;
        $this->matchedRuleIndices = $matchedRuleIndices;
        $this->matchedRules = $matchedRules;
        $this->evaluationTimeMs = $evaluationTimeMs;
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function getHitPolicy(): string
    {
        return $this->hitPolicy;
    }

    public function getOutputs(): mixed
    {
        return $this->outputs;
    }

    public function hasMatch(): bool
    {
        return !empty($this->matchedRuleIndices);
    }

    public function getMatchedRuleIndices(): array
    {
        return $this->matchedRuleIndices;
    }

    /**
     * @return array<DecisionRule>
     */
    public function getMatchedRules(): array
    {
        return $this->matchedRules;
    }

    public function getEvaluationTimeMs(): float
    {
        return $this->evaluationTimeMs;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (is_array($this->outputs) && array_key_exists($key, $this->outputs)) {
            return $this->outputs[$key];
        }
        return $default;
    }

    public function __get(string $name): mixed
    {
        return $this->get($name);
    }

    // ArrayAccess implementation
    public function offsetExists(mixed $offset): bool
    {
        return is_array($this->outputs) && array_key_exists((string)$offset, $this->outputs);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get((string)$offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (is_array($this->outputs)) {
            $this->outputs[(string)$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        if (is_array($this->outputs)) {
            unset($this->outputs[(string)$offset]);
        }
    }

    public function jsonSerialize(): array
    {
        return [
            'tableName' => $this->tableName,
            'hitPolicy' => $this->hitPolicy,
            'outputs' => $this->outputs,
            'matchedRules' => $this->matchedRuleIndices,
            'evaluationTimeMs' => $this->evaluationTimeMs,
        ];
    }
}
