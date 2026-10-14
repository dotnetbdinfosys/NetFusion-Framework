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

namespace NetFusion\Core\WAF\Rules;

use NetFusion\Core\WAF\IP\ThreatContext;
use JsonSerializable;

class VirtualPatchEngine implements JsonSerializable
{
    /**
     * @var array<string, WAFRule>
     */
    protected array $rules = [];

    /**
     * Add or update a virtual patch rule.
     */
    public function addRule(WAFRule $rule): void
    {
        $this->rules[$rule->getId()] = $rule;
    }

    /**
     * Add a quick virtual patch definition.
     */
    public function patch(
        string $id,
        string $field,
        string $operator,
        mixed $pattern,
        string $description = '',
        string $action = 'BLOCK',
        float $score = 5.0
    ): WAFRule {
        $rule = new WAFRule(
            id: $id,
            description: $description,
            field: $field,
            operator: $operator,
            pattern: $pattern,
            action: $action,
            score: $score
        );

        $this->addRule($rule);
        return $rule;
    }

    public function getRule(string $id): ?WAFRule
    {
        return $this->rules[$id] ?? null;
    }

    public function hasRule(string $id): bool
    {
        return isset($this->rules[$id]);
    }

    public function removeRule(string $id): bool
    {
        if (isset($this->rules[$id])) {
            unset($this->rules[$id]);
            return true;
        }
        return false;
    }

    /**
     * @return array<WAFRule>
     */
    public function getRules(): array
    {
        return array_values($this->rules);
    }

    /**
     * Evaluate all virtual patch rules against a ThreatContext.
     *
     * @return array<WAFRule> Matched rules
     */
    public function evaluate(ThreatContext $context): array
    {
        $matched = [];

        foreach ($this->rules as $rule) {
            if ($rule->matches($context)) {
                $matched[] = $rule;
                $context->getAnomalyScore()->addMatch(
                    ruleId: $rule->getId(),
                    detector: 'VirtualPatchEngine',
                    field: $rule->getField(),
                    points: $rule->getScore(),
                    matchSnippet: (string)$rule->getPattern(),
                    message: $rule->getDescription()
                );

                if ($rule->getAction() === 'BLOCK') {
                    $context->setAction('BLOCK', $rule->getDescription(), $rule->getId());
                } elseif ($rule->getAction() === 'CHALLENGE' && !$context->isBlocked()) {
                    $context->setAction('CHALLENGE', $rule->getDescription(), $rule->getId());
                }
            }
        }

        return $matched;
    }

    public function count(): int
    {
        return count($this->rules);
    }

    public function clear(): void
    {
        $this->rules = [];
    }

    public function jsonSerialize(): array
    {
        $serialized = [];
        foreach ($this->rules as $id => $rule) {
            $serialized[$id] = $rule->jsonSerialize();
        }
        return $serialized;
    }

    public static function fromArray(array $data): self
    {
        $engine = new self();
        foreach ($data as $ruleData) {
            $engine->addRule(WAFRule::fromArray((array)$ruleData));
        }
        return $engine;
    }
}
