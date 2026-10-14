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

namespace NetFusion\Core\Auth\Policy;

use NetFusion\Core\Auth\Policy\Contracts\PolicyEngineInterface;
use NetFusion\Core\Auth\Policy\Contracts\PolicyStoreInterface;
use NetFusion\Core\Auth\Policy\Evaluator\ConditionEvaluator;
use NetFusion\Core\Auth\Policy\Store\ArrayPolicyStore;

class PolicyEngine implements PolicyEngineInterface
{
    public function __construct(
        protected ?PolicyStoreInterface $store = null,
        protected Effect $defaultEffect = Effect::DENY
    ) {
        $this->store = $store ?? new ArrayPolicyStore();
    }

    public function setStore(PolicyStoreInterface $store): self
    {
        $this->store = $store;
        return $this;
    }

    public function getStore(): PolicyStoreInterface
    {
        return $this->store;
    }

    public function register(Policy $policy): self
    {
        $this->store->save($policy);
        return $this;
    }

    public function define(string $id, callable $callback, int $priority = 0): Policy
    {
        $policy = Policy::make($id, $id, $priority);
        $callback($policy);
        $this->register($policy);
        return $policy;
    }

    public function evaluate(EvaluationContext $context): Decision
    {
        $policies = $this->store->all();
        $allowedBy = null;

        foreach ($policies as $policy) {
            if (!$policy->enabled) {
                continue;
            }

            foreach ($policy->getRules() as $rule) {
                // Check if action and resource type match
                if (!$rule->matchesAction($context->action)) {
                    continue;
                }

                if (!$rule->matchesResource($context->resource)) {
                    continue;
                }

                // Evaluate conditions
                if (!ConditionEvaluator::evaluate($rule->conditions, $context)) {
                    continue;
                }

                // Explicit DENY rule matched: Immediate DENY overriding everything!
                if ($rule->effect->isDeny()) {
                    return Decision::deny(
                        policy: $policy->id,
                        rule: $rule->id,
                        reason: "Explicitly denied by policy [{$policy->id}] rule [{$rule->id}]."
                    );
                }

                // ALLOW rule matched
                if ($rule->effect->isAllow() && $allowedBy === null) {
                    $allowedBy = [
                        'policy' => $policy->id,
                        'rule' => $rule->id,
                        'reason' => $rule->description ?? "Access granted by policy [{$policy->id}] rule [{$rule->id}].",
                    ];
                }
            }
        }

        if ($allowedBy !== null) {
            return Decision::allow(
                policy: $allowedBy['policy'],
                rule: $allowedBy['rule'],
                reason: $allowedBy['reason']
            );
        }

        return Decision::deny(
            policy: null,
            rule: null,
            reason: "Default deny: No matching allow rule satisfied the evaluation context."
        );
    }

    public function allows(mixed $subject, string $action, mixed $resource, array $environment = []): bool
    {
        $context = new EvaluationContext($subject, $action, $resource, $environment);
        return $this->evaluate($context)->isAllowed();
    }

    public function denies(mixed $subject, string $action, mixed $resource, array $environment = []): bool
    {
        return !$this->allows($subject, $action, $resource, $environment);
    }
}
