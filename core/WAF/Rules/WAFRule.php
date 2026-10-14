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

class WAFRule implements JsonSerializable
{
    /**
     * @param string $id Unique rule identifier (e.g. 'VPATCH-2026-001')
     * @param string $description Rule description
     * @param string $field Target request field (e.g. 'uri', 'method', 'headers.user-agent', 'query.id', 'body.payload')
     * @param string $operator Matching operator: 'regex', 'contains', 'equals', 'starts_with', 'ends_with', 'gt', 'lt'
     * @param mixed $pattern Pattern to match against
     * @param string $action Enforcement action: 'BLOCK', 'ALLOW', 'CHALLENGE', 'LOG'
     * @param float $score Anomaly score contribution
     * @param bool $enabled Active state
     * @param array<string> $transformers List of transformations to apply before matching (e.g. 'url_decode', 'lowercase', 'html_decode')
     */
    public function __construct(
        protected string $id,
        protected string $description,
        protected string $field,
        protected string $operator,
        protected mixed $pattern,
        protected string $action = 'BLOCK',
        protected float $score = 5.0,
        protected bool $enabled = true,
        protected array $transformers = ['url_decode', 'lowercase']
    ) {
        $this->action = strtoupper($this->action);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getOperator(): string
    {
        return $this->operator;
    }

    public function getPattern(): mixed
    {
        return $this->pattern;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getScore(): float
    {
        return $this->score;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    /**
     * Evaluate rule against a ThreatContext.
     */
    public function matches(ThreatContext $context): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $value = $this->extractFieldValue($this->field, $context);
        if ($value === null) {
            return false;
        }

        if (is_array($value)) {
            foreach ($value as $subVal) {
                if ($this->matchValue($subVal)) {
                    return true;
                }
            }
            return false;
        }

        return $this->matchValue($value);
    }

    /**
     * Test single scalar value against rule pattern after applying transformations.
     */
    protected function matchValue(mixed $val): bool
    {
        if (!is_scalar($val)) {
            return false;
        }

        $str = (string)$val;
        foreach ($this->transformers as $t) {
            $str = match ($t) {
                'url_decode' => rawurldecode(urldecode($str)),
                'lowercase' => mb_strtolower($str, 'UTF-8'),
                'html_decode' => html_entity_decode($str, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'trim' => trim($str),
                default => $str,
            };
        }

        $pattern = $this->pattern;
        if (is_string($pattern) && in_array('lowercase', $this->transformers, true)) {
            $pattern = mb_strtolower($pattern, 'UTF-8');
        }

        return match ($this->operator) {
            'regex' => (bool)@preg_match((string)$this->pattern, $str),
            'contains' => str_contains($str, (string)$pattern),
            'equals' => $str === (string)$pattern,
            'starts_with' => str_starts_with($str, (string)$pattern),
            'ends_with' => str_ends_with($str, (string)$pattern),
            'gt' => (float)$str > (float)$pattern,
            'lt' => (float)$str < (float)$pattern,
            default => false,
        };
    }

    /**
     * Extract target field value from ThreatContext.
     */
    protected function extractFieldValue(string $field, ThreatContext $context): mixed
    {
        if ($field === 'uri') return $context->getUri();
        if ($field === 'method') return $context->getMethod();
        if ($field === 'ip') return $context->getIp();
        if ($field === 'body') return $context->getBody();

        if (str_starts_with($field, 'headers.')) {
            $subKey = strtolower(substr($field, 8));
            $headers = array_change_key_case($context->getHeaders(), CASE_LOWER);
            return $headers[$subKey] ?? null;
        }

        if (str_starts_with($field, 'query.')) {
            $subKey = substr($field, 6);
            $query = $context->getQuery();
            return $query[$subKey] ?? null;
        }

        if (str_starts_with($field, 'body.')) {
            $subKey = substr($field, 5);
            $body = $context->getBody();
            if (is_array($body)) {
                return $body[$subKey] ?? null;
            }
            return null;
        }

        if (str_starts_with($field, 'cookies.')) {
            $subKey = substr($field, 8);
            $cookies = $context->getCookies();
            return $cookies[$subKey] ?? null;
        }

        return null;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'field' => $this->field,
            'operator' => $this->operator,
            'pattern' => $this->pattern,
            'action' => $this->action,
            'score' => $this->score,
            'enabled' => $this->enabled,
            'transformers' => $this->transformers,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (string)($data['id'] ?? uniqid('rule_')),
            description: (string)($data['description'] ?? ''),
            field: (string)($data['field'] ?? 'uri'),
            operator: (string)($data['operator'] ?? 'regex'),
            pattern: $data['pattern'] ?? '.*',
            action: (string)($data['action'] ?? 'BLOCK'),
            score: (float)($data['score'] ?? 5.0),
            enabled: (bool)($data['enabled'] ?? true),
            transformers: (array)($data['transformers'] ?? ['url_decode', 'lowercase'])
        );
    }
}
