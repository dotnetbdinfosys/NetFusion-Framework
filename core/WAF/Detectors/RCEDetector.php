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

class RCEDetector implements ThreatDetectorInterface
{
    /**
     * @var array<string, array{pattern: string, score: float, message: string}>
     */
    protected array $rules = [
        'RCE_COMMAND_CHAINING' => [
            'pattern' => '/(\||;|&|`|\$\()\s*(cat|ls|id|whoami|uname|curl|wget|bash|sh|nc|python|perl|php|powershell|cmd)\b/i',
            'score' => 5.0,
            'message' => 'RCE: Command separator chaining and shell execution attempt',
        ],
        'RCE_PHP_EXEC_SINK' => [
            'pattern' => '/\b(system|exec|passthru|shell_exec|popen|proc_open|eval|assert)\s*\(.*?\)/i',
            'score' => 5.0,
            'message' => 'RCE: Direct PHP process execution sink invocation detected',
        ],
        'RCE_REVERSE_SHELL' => [
            'pattern' => '/\b(bash\s+-i|nc\s+-e|\/dev\/tcp\/[0-9\.]+|powershell\s+-enc|cmd\.exe\s+\/c)\b/i',
            'score' => 5.0,
            'message' => 'RCE: Reverse shell or encoded payload invocation detected',
        ],
        'RCE_UNIX_BINARIES' => [
            'pattern' => '/\/(bin|usr\/bin|sbin|usr\/sbin)\/(sh|bash|zsh|dash|python|perl|curl|wget|nc|chmod|chown)\b/i',
            'score' => 4.5,
            'message' => 'RCE: Direct execution of Unix system binaries detected',
        ],
    ];

    public function getName(): string
    {
        return 'RCEDetector';
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

        $normalized = rawurldecode(urldecode($value));

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
}
