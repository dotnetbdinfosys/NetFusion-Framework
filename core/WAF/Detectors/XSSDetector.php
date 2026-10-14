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

class XSSDetector implements ThreatDetectorInterface
{
    /**
     * @var array<string, array{pattern: string, score: float, message: string}>
     */
    protected array $rules = [
        'XSS_SCRIPT_TAG' => [
            'pattern' => '/<script\b[^>]*>.*?<\/script>|<script\b[^>]*>/is',
            'score' => 5.0,
            'message' => 'XSS: Explicit <script> tag detected',
        ],
        'XSS_EVENT_HANDLER' => [
            'pattern' => '/\b(onload|onerror|onclick|onmouseover|onmouseenter|onfocus|onblur|onchange|onsubmit|onkeydown|onkeyup|onpointerdown)\s*=\s*[\'"][^\'"]*[\'"]|\b(onload|onerror|onclick)\s*=/i',
            'score' => 4.5,
            'message' => 'XSS: Inline JavaScript event handler injection detected',
        ],
        'XSS_JAVASCRIPT_URI' => [
            'pattern' => '/(javascript|vbscript|data\s*:\s*text\/html)\s*:/i',
            'score' => 4.5,
            'message' => 'XSS: Malicious javascript/data pseudo-protocol URI detected',
        ],
        'XSS_DANGEROUS_TAG' => [
            'pattern' => '/<(iframe|object|embed|svg|link|meta|base|applet)\b[^>]*>/i',
            'score' => 4.0,
            'message' => 'XSS: High-risk HTML element injection detected',
        ],
        'XSS_DOM_EXPLOIT' => [
            'pattern' => '/\b(document\.cookie|document\.location|window\.location|document\.write|eval\s*\(|alert\s*\(|prompt\s*\(|confirm\s*\()/i',
            'score' => 3.5,
            'message' => 'XSS: Suspicious DOM API or execution sink invocation detected',
        ],
    ];

    public function getName(): string
    {
        return 'XSSDetector';
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

    protected function normalize(string $input): string
    {
        // Decode URL encoding and HTML entities
        $decoded = rawurldecode(urldecode($input));
        $decoded = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Strip null bytes and control chars
        return str_replace(["\0", "\r", "\t", "\n"], ' ', $decoded);
    }
}
