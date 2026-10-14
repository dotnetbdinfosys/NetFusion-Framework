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

use NetFusion\Core\RuleEngine\Contracts\DecisionTableInterface;
use NetFusion\Core\RuleEngine\Contracts\FeelEvaluatorInterface;
use NetFusion\Core\RuleEngine\Exceptions\RuleEvaluationException;
use NetFusion\Core\RuleEngine\FEEL\FeelEvaluator;

class DecisionTable implements DecisionTableInterface
{
    protected string $name;
    protected string $hitPolicy;
    /** @var array<InputClause> */
    protected array $inputs = [];
    /** @var array<OutputClause> */
    protected array $outputs = [];
    /** @var array<DecisionRule> */
    protected array $rules = [];
    protected FeelEvaluatorInterface $feelEvaluator;

    public function __construct(
        string $name,
        string $hitPolicy = HitPolicy::FIRST,
        array $inputs = [],
        array $outputs = [],
        array $rules = [],
        ?FeelEvaluatorInterface $feelEvaluator = null
    ) {
        $this->name = trim($name);
        $this->hitPolicy = strtoupper($hitPolicy);
        $this->inputs = $inputs;
        $this->outputs = $outputs;
        $this->rules = $rules;
        $this->feelEvaluator = $feelEvaluator ?? new FeelEvaluator();
    }

    public static function make(string $name, string $hitPolicy = HitPolicy::FIRST): self
    {
        return new self($name, $hitPolicy);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getHitPolicy(): string
    {
        return $this->hitPolicy;
    }

    public function addInput(InputClause $input): self
    {
        $this->inputs[] = $input;
        return $this;
    }

    public function addOutput(OutputClause $output): self
    {
        $this->outputs[] = $output;
        return $this;
    }

    public function addRule(DecisionRule $rule): self
    {
        $rule->setIndex(count($this->rules) + 1);
        $this->rules[] = $rule;
        return $this;
    }

    /**
     * @return array<InputClause>
     */
    public function getInputs(): array
    {
        return $this->inputs;
    }

    /**
     * @return array<OutputClause>
     */
    public function getOutputs(): array
    {
        return $this->outputs;
    }

    /**
     * @return array<DecisionRule>
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * Evaluate the decision table with given context.
     *
     * @param array<string, mixed> $context
     */
    public function evaluate(array $context): DecisionResult
    {
        $start = microtime(true);

        // 1. Resolve input expressions from context
        $inputValues = [];
        foreach ($this->inputs as $idx => $inputClause) {
            $expr = $inputClause->getExpression();
            $val = $this->feelEvaluator->evaluate($expr, $context);
            $inputValues[$idx] = $val;
        }

        // 2. Evaluate all rules against inputs
        /** @var array<array{rule: DecisionRule, outputs: array<string, mixed>}> $matchedList */
        $matchedList = [];

        foreach ($this->rules as $rule) {
            $inputEntries = $rule->getInputEntries();
            $matches = true;

            foreach ($this->inputs as $idx => $inputClause) {
                $unaryTest = $inputEntries[$idx] ?? '-';
                $val = $inputValues[$idx] ?? null;

                if (!$this->feelEvaluator->evaluateUnaryTest($unaryTest, $val, $context)) {
                    $matches = false;
                    break;
                }
            }

            if ($matches) {
                // Construct output map for this matched rule
                $outputEntries = $rule->getOutputEntries();
                $outputMap = [];

                foreach ($this->outputs as $outIdx => $outputClause) {
                    $rawOutput = $outputEntries[$outIdx] ?? $outputClause->getDefaultValue();
                    if (is_string($rawOutput)) {
                        $outputMap[$outputClause->getName()] = $this->feelEvaluator->evaluate($rawOutput, $context);
                    } else {
                        $outputMap[$outputClause->getName()] = $rawOutput;
                    }
                }

                $matchedList[] = [
                    'rule' => $rule,
                    'outputs' => $outputMap,
                ];

                // Fast-exit for FIRST policy
                if ($this->hitPolicy === HitPolicy::FIRST) {
                    break;
                }
            }
        }

        $elapsedMs = (microtime(true) - $start) * 1000.0;
        $matchedIndices = array_map(fn($m) => $m['rule']->getIndex(), $matchedList);
        $matchedRules = array_map(fn($m) => $m['rule'], $matchedList);

        // 3. Apply Hit Policy
        $finalOutput = $this->applyHitPolicy($matchedList);

        return new DecisionResult(
            $this->name,
            $this->hitPolicy,
            $finalOutput,
            $matchedIndices,
            $matchedRules,
            $elapsedMs
        );
    }

    /**
     * @param array<array{rule: DecisionRule, outputs: array<string, mixed>}> $matches
     */
    protected function applyHitPolicy(array $matches): mixed
    {
        $defaultOutputs = [];
        foreach ($this->outputs as $outputClause) {
            $defaultOutputs[$outputClause->getName()] = $outputClause->getDefaultValue();
        }

        if (empty($matches)) {
            return count($this->outputs) === 1 ? reset($defaultOutputs) : $defaultOutputs;
        }

        switch ($this->hitPolicy) {
            case HitPolicy::FIRST:
                $first = $matches[0]['outputs'];
                return count($this->outputs) === 1 ? reset($first) : $first;

            case HitPolicy::UNIQUE:
                if (count($matches) > 1) {
                    throw new RuleEvaluationException(
                        sprintf("Decision table '%s' violation: multiple rules matched under UNIQUE hit policy", $this->name)
                    );
                }
                $first = $matches[0]['outputs'];
                return count($this->outputs) === 1 ? reset($first) : $first;

            case HitPolicy::ANY:
                // All matching rules must produce identical outputs
                $first = $matches[0]['outputs'];
                foreach ($matches as $m) {
                    if ($m['outputs'] != $first) {
                        throw new RuleEvaluationException(
                            sprintf("Decision table '%s' violation: inconsistent outputs matched under ANY hit policy", $this->name)
                        );
                    }
                }
                return count($this->outputs) === 1 ? reset($first) : $first;

            case HitPolicy::PRIORITY:
                // Sort matches by first output's priority values if defined
                $firstOutputClause = $this->outputs[0] ?? null;
                $priorityList = $firstOutputClause?->getPriorityValues();
                if (!empty($priorityList)) {
                    usort($matches, function ($a, $b) use ($firstOutputClause, $priorityList) {
                        $name = $firstOutputClause->getName();
                        $posA = array_search($a['outputs'][$name] ?? null, $priorityList);
                        $posB = array_search($b['outputs'][$name] ?? null, $priorityList);
                        $rankA = $posA !== false ? $posA : PHP_INT_MAX;
                        $rankB = $posB !== false ? $posB : PHP_INT_MAX;
                        return $rankA <=> $rankB;
                    });
                }
                $best = $matches[0]['outputs'];
                return count($this->outputs) === 1 ? reset($best) : $best;

            case HitPolicy::RULE_ORDER:
            case HitPolicy::COLLECT_LIST:
                $list = array_map(function ($m) {
                    return count($this->outputs) === 1 ? reset($m['outputs']) : $m['outputs'];
                }, $matches);
                return $list;

            case HitPolicy::COLLECT_COUNT:
                return count($matches);

            case HitPolicy::COLLECT_SUM:
                $sum = 0.0;
                foreach ($matches as $m) {
                    $val = reset($m['outputs']);
                    $sum += (float) $val;
                }
                return $sum;

            case HitPolicy::COLLECT_MIN:
                $min = PHP_FLOAT_MAX;
                foreach ($matches as $m) {
                    $val = (float) reset($m['outputs']);
                    $min = min($min, $val);
                }
                return $min === PHP_FLOAT_MAX ? 0.0 : $min;

            case HitPolicy::COLLECT_MAX:
                $max = -PHP_FLOAT_MAX;
                foreach ($matches as $m) {
                    $val = (float) reset($m['outputs']);
                    $max = max($max, $val);
                }
                return $max === -PHP_FLOAT_MAX ? 0.0 : $max;

            default:
                $first = $matches[0]['outputs'];
                return count($this->outputs) === 1 ? reset($first) : $first;
        }
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'hitPolicy' => $this->hitPolicy,
            'inputs' => array_map(fn($i) => $i->jsonSerialize(), $this->inputs),
            'outputs' => array_map(fn($o) => $o->jsonSerialize(), $this->outputs),
            'rules' => array_map(fn($r) => $r->jsonSerialize(), $this->rules),
        ];
    }
}
