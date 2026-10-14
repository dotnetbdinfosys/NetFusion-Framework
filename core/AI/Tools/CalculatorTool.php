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

namespace NetFusion\Core\AI\Tools;

use InvalidArgumentException;
use NetFusion\Core\AI\Contracts\ToolInterface;

class CalculatorTool implements ToolInterface
{
    public function getName(): string
    {
        return 'calculator';
    }

    public function getDescription(): string
    {
        return 'Evaluates a mathematical expression (e.g. "2 + 2", "15 * 4 / 2", "pow(2, 8)", "sqrt(144)").';
    }

    public function getParametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'expression' => [
                    'type' => 'string',
                    'description' => 'The mathematical expression to evaluate.',
                ],
            ],
            'required' => ['expression'],
        ];
    }

    public function execute(array $arguments): mixed
    {
        $expr = trim((string)($arguments['expression'] ?? ''));
        if ($expr === '') {
            throw new InvalidArgumentException("Mathematical expression cannot be empty.");
        }

        // Sanitization: Allow numbers, basic operators, parenthesis, and safe math functions
        $allowedPattern = '/^[\d\s\+\-\*\/\%\.\(\)\,\^]|sqrt|pow|abs|round|floor|ceil|min|max|sin|cos|tan|log|pi/i';
        
        // Remove allowed tokens to check if any invalid characters remain
        $cleaned = preg_replace('/\b(sqrt|pow|abs|round|floor|ceil|min|max|sin|cos|tan|log|pi)\b/i', '', $expr);
        if (preg_match('/[^\d\s\+\-\*\/\%\.\(\)\,\^]/', $cleaned)) {
            throw new InvalidArgumentException("Unsafe or invalid characters detected in math expression: " . $expr);
        }

        // Replace '^' with '**'
        $sanitized = str_replace('^', '**', $expr);

        try {
            // Evaluate safely using eval on strictly validated expression
            $result = @eval('return ' . $sanitized . ';');
            if ($result === false && !is_numeric($result)) {
                throw new InvalidArgumentException("Unable to evaluate expression: " . $expr);
            }
            return $result;
        } catch (\Throwable $e) {
            throw new InvalidArgumentException("Math evaluation error: " . $e->getMessage());
        }
    }

    public function toFunctionDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->getName(),
                'description' => $this->getDescription(),
                'parameters' => $this->getParametersSchema(),
            ],
        ];
    }
}
