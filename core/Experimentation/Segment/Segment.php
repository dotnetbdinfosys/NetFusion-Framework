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

namespace NetFusion\Core\Experimentation\Segment;

use NetFusion\Core\Experimentation\EvaluationContext;

class Segment
{
    /**
     * @param string      $name      Segment identifier (e.g. 'beta_testers', 'enterprise_us')
     * @param list<Rule>  $rules     Collection of evaluation rules
     * @param string      $matchType 'all' (AND) or 'any' (OR)
     * @param string|null $description
     */
    public function __construct(
        public readonly string $name,
        public readonly array $rules = [],
        public readonly string $matchType = 'all',
        public readonly ?string $description = null
    ) {}

    /**
     * Determine if evaluation context matches this segment.
     */
    public function matches(EvaluationContext $context): bool
    {
        if (empty($this->rules)) {
            return true;
        }

        if ($this->matchType === 'any') {
            foreach ($this->rules as $rule) {
                if ($rule->matches($context)) {
                    return true;
                }
            }
            return false;
        }

        // 'all' match
        foreach ($this->rules as $rule) {
            if (!$rule->matches($context)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Build segment from array definition.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $rules = [];
        foreach ((array)($data['rules'] ?? []) as $r) {
            $rules[] = $r instanceof Rule ? $r : Rule::fromArray((array)$r);
        }

        return new self(
            name: (string)($data['name'] ?? 'default_segment'),
            rules: $rules,
            matchType: (string)($data['match_type'] ?? $data['matchType'] ?? 'all'),
            description: isset($data['description']) ? (string)$data['description'] : null
        );
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'match_type' => $this->matchType,
            'description' => $this->description,
            'rules' => array_map(fn(Rule $r) => $r->toArray(), $this->rules),
        ];
    }
}
