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

class PathTraversalDetector implements ThreatDetectorInterface
{
    /**
     * @var array<string, array{pattern: string, score: float, message: string}>
     */
    protected array $rules = [
        'TRAVERSAL_DOT_DOT' => [
            'pattern' => '/(\.\.[\/\\\\]|\%2e\%2e[\/\\\\]|\%2e\%2e\%2f|\%2e\%2e\%5c|\.\.\%2f|\.\.\%5c)/i',
            'score' => 5.0,
            'message' => 'Path Traversal: Relative directory stepping (../) attempt detected',
        ],
        'TRAVERSAL_UNIX_SYSTEM_FILE' => [
            'pattern' => '/(\/etc\/(passwd|shadow|hosts|group|crontab|issue|resolv\.conf)|\/proc\/self\/environ|\/var\/log\/)/i',
            'score' => 5.0,
            'message' => 'Path Traversal: Direct access to critical Unix system files',
        ],
        'TRAVERSAL_WINDOWS_SYSTEM_FILE' => [
            'pattern' => '/([a-zA-Z]:[\/\\\\](windows|winnt|system32|boot\.ini|win\.ini)|win\.ini|boot\.ini)/i',
            'score' => 5.0,
            'message' => 'Path Traversal: Direct access to critical Windows system files',
        ],
        'TRAVERSAL_NULL_BYTE' => [
            'pattern' => '/(\%00|\0)/',
            'score' => 4.5,
            'message' => 'Path Traversal: Poison null byte filename truncation attempt',
        ],
    ];

    public function getName(): string
    {
        return 'PathTraversalDetector';
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

        $raw = $value;
        $decoded = rawurldecode(urldecode($value));

        foreach ($this->rules as $ruleId => $rule) {
            if (preg_match($rule['pattern'], $raw, $matches) || preg_match($rule['pattern'], $decoded, $matches)) {
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
}
