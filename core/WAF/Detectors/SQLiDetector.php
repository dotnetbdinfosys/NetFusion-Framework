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

namespace NetFusion\Core\WAF\Detectors;

use NetFusion\Core\WAF\Contracts\ThreatDetectorInterface;
use NetFusion\Core\WAF\IP\ThreatContext;

class SQLiDetector implements ThreatDetectorInterface
{
    /**
     * Regex patterns for detecting SQL Injection attempts.
     * @var array<string, array{pattern: string, score: float, message: string}>
     */
    protected array $rules = [
        'SQLI_UNION' => [
            'pattern' => '/\bunion\b\s+(all\s+)?\bselect\b/i',
            'score' => 5.0,
            'message' => 'SQLi: Classic UNION SELECT injection attempt detected',
        ],
        'SQLI_BOOLEAN_OR' => [
            'pattern' => '/(\'|"|`|\b)(or|and)\b\s+([0-9]+=[0-9]+|[\'"][^\'"]*[\'"]=[\'"][^\'"]*|true|false|\d+=\d+)/i',
            'score' => 5.0,
            'message' => 'SQLi: Boolean-based OR/AND clause condition injection detected',
        ],
        'SQLI_STACKED_QUERY' => [
            'pattern' => '/;\s*(drop|delete|insert|update|create|alter|truncate|exec|execute)\b/i',
            'score' => 5.0,
            'message' => 'SQLi: Stacked destructive SQL statement execution attempt',
        ],
        'SQLI_COMMENT_INJECTION' => [
            'pattern' => '/(\'|"|`)\s*(--|\/\*|#)/i',
            'score' => 3.5,
            'message' => 'SQLi: SQL comment evasion pattern detected',
        ],
        'SQLI_BLIND_TIME' => [
            'pattern' => '/\b(sleep|benchmark|waitfor\s+delay|pg_sleep)\s*\(/i',
            'score' => 5.0,
            'message' => 'SQLi: Blind time-based sleep/benchmark injection detected',
        ],
        'SQLI_METADATA_DISCOVERY' => [
            'pattern' => '/\b(information_schema|sys\.tables|pg_catalog|sqlite_master|sqlite_schema)\b/i',
            'score' => 4.5,
            'message' => 'SQLi: Database metadata and schema enumeration attempt',
        ],
        'SQLI_HEX_CHAR' => [
            'pattern' => '/\b(char|chr|0x[0-9a-fA-F]{4,})\s*\(/i',
            'score' => 3.0,
            'message' => 'SQLi: Hexadecimal or ASCII character code evasion attempt',
        ],
    ];

    public function getName(): string
    {
        return 'SQLiDetector';
    }

    public function inspect(string $field, mixed $value, ThreatContext $context, AnomalyScore $anomalyScore): void
    {
        if (is_array($value)) {
            foreach ($value as $k => $subVal) {
                $this->inspect($field . '.' . $k, $subVal, $context, $anomalyScore);
            }
            return;
        }

        if (!is_string($value) || $value === '') {
            return;
        }

        // Normalize string for evasion checks
        $normalized = $this->normalize($value);

        foreach ($this->rules as $ruleId => $rule) {
            if (preg_match($rule['pattern'], $normalized, $matches)) {
                $snippet = mb_strimwidth($matches[0], 0, 50, '...');
                $anomalyScore->addMatch(
                    ruleId: $ruleId,
                    detector: $this->getName(),
                    field: $field,
                    points: $rule['score'],
                    matchSnippet: $snippet,
                    message: $rule['message']
                );
            }
        }
    }

    /**
     * Normalize URL encoded characters and multi-line comments to prevent filter bypass.
     */
    protected function normalize(string $input): string
    {
        $decoded = rawurldecode(urldecode($input));
        // Remove inline comment obfuscation like UN/**/ION SEL/**/ECT
        $cleaned = preg_replace('/\/\*.*?\*\//s', ' ', $decoded);
        return (string)$cleaned;
    }
}
