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

use JsonSerializable;

class Policy implements JsonSerializable
{
    /** @var Rule[] */
    protected array $rules = [];

    /**
     * @param string $id
     * @param string $name
     * @param Rule[] $rules
     * @param int $priority (higher evaluated first)
     * @param bool $enabled
     * @param string|null $description
     */
    public function __construct(
        public string $id,
        public string $name,
        array $rules = [],
        public int $priority = 0,
        public bool $enabled = true,
        public ?string $description = null
    ) {
        foreach ($rules as $rule) {
            $this->addRule($rule);
        }
    }

    public static function make(string $id, string $name, int $priority = 0): self
    {
        return new self($id, $name, [], $priority);
    }

    public function addRule(Rule $rule): self
    {
        $this->rules[$rule->id] = $rule;
        return $this;
    }

    public function allow(
        string $idOrAction,
        mixed $actionsOrResources = '*',
        mixed $resourcesOrConditions = '*',
        mixed $conditions = null,
        ?string $description = null
    ): self {
        return $this->createAndAddRule(Effect::ALLOW, $idOrAction, $actionsOrResources, $resourcesOrConditions, $conditions, $description);
    }

    public function deny(
        string $idOrAction,
        mixed $actionsOrResources = '*',
        mixed $resourcesOrConditions = '*',
        mixed $conditions = null,
        ?string $description = null
    ): self {
        return $this->createAndAddRule(Effect::DENY, $idOrAction, $actionsOrResources, $resourcesOrConditions, $conditions, $description);
    }

    protected function createAndAddRule(
        Effect $effect,
        string $idOrAction,
        mixed $actionsOrResources,
        mixed $resourcesOrConditions,
        mixed $conditions,
        ?string $description
    ): self {
        // Case 1: allow('documents:read', 'documents:*', ['conditions' => ..., 'description' => ...])
        // or allow('documents:read', 'documents:*')
        if (
            str_contains($idOrAction, ':') ||
            str_contains($idOrAction, '*') ||
            (is_string($actionsOrResources) && (str_contains($actionsOrResources, ':') || str_contains($actionsOrResources, '*'))) ||
            is_array($resourcesOrConditions) && (!empty($resourcesOrConditions) && !is_numeric(array_key_first($resourcesOrConditions)))
        ) {
            $id = uniqid('rule_');
            $actions = $idOrAction;
            $resources = is_string($actionsOrResources) || (is_array($actionsOrResources) && is_numeric(array_key_first($actionsOrResources)))
                ? $actionsOrResources
                : '*';

            $opts = is_array($resourcesOrConditions) ? $resourcesOrConditions : [];
            $conds = $opts['conditions'] ?? (is_array($resourcesOrConditions) && (isset($resourcesOrConditions['$or']) || isset($resourcesOrConditions['$and']) || isset($resourcesOrConditions['$not'])) ? $resourcesOrConditions : $conditions);
            $desc = $opts['description'] ?? (is_string($resourcesOrConditions) ? $resourcesOrConditions : $description);

            $rule = new Rule($id, $effect, $actions, $resources, $conds, $desc);
            return $this->addRule($rule);
        }

        // Case 2: Full explicit signature: allow('rule_id', 'documents:read', 'documents:*', $conditions, $description)
        $rule = new Rule($idOrAction, $effect, $actionsOrResources, $resourcesOrConditions, $conditions, $description);
        return $this->addRule($rule);
    }

    /**
     * @return Rule[]
     */
    public function getRules(): array
    {
        return array_values($this->rules);
    }

    public function getRule(string $id): ?Rule
    {
        return $this->rules[$id] ?? null;
    }

    public function count(): int
    {
        return count($this->rules);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'priority' => $this->priority,
            'enabled' => $this->enabled,
            'description' => $this->description,
            'rules' => array_map(fn(Rule $r) => $r->toArray(), array_values($this->rules)),
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
