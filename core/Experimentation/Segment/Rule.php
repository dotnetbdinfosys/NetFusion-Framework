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

class Rule
{
    /**
     * @param string $attribute Dot-notated attribute name (e.g. 'country', 'user.plan', 'version')
     * @param string $operator  Comparison operator
     * @param mixed  $value     Target value to compare against
     */
    public function __construct(
        public readonly string $attribute,
        public readonly string $operator,
        public readonly mixed $value = null
    ) {}

    /**
     * Evaluate rule against context.
     */
    public function matches(EvaluationContext $context): bool
    {
        $actual = $context->get($this->attribute);

        return match ($this->operator) {
            'eq', '==' => $actual == $this->value,
            'strict_eq', '===' => $actual === $this->value,
            'neq', '!=' => $actual != $this->value,
            'in' => is_array($this->value) && in_array($actual, $this->value, false),
            'not_in' => is_array($this->value) && !in_array($actual, $this->value, false),
            'gt', '>' => is_numeric($actual) && is_numeric($this->value) && $actual > $this->value,
            'gte', '>=' => is_numeric($actual) && is_numeric($this->value) && $actual >= $this->value,
            'lt', '<' => is_numeric($actual) && is_numeric($this->value) && $actual < $this->value,
            'lte', '<=' => is_numeric($actual) && is_numeric($this->value) && $actual <= $this->value,
            'contains' => is_string($actual) && str_contains($actual, (string)$this->value),
            'starts_with' => is_string($actual) && str_starts_with($actual, (string)$this->value),
            'ends_with' => is_string($actual) && str_ends_with($actual, (string)$this->value),
            'regex' => is_string($actual) && @preg_match((string)$this->value, $actual) === 1,
            'semver_gte' => is_string($actual) && version_compare($actual, (string)$this->value, '>='),
            'semver_lte' => is_string($actual) && version_compare($actual, (string)$this->value, '<='),
            'exists' => $actual !== null,
            'not_exists' => $actual === null,
            default => false,
        };
    }

    /**
     * Reconstruct rule from array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            attribute: (string)($data['attribute'] ?? $data['attr'] ?? ''),
            operator: (string)($data['operator'] ?? $data['op'] ?? 'eq'),
            value: $data['value'] ?? null
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
            'attribute' => $this->attribute,
            'operator' => $this->operator,
            'value' => $this->value,
        ];
    }
}
