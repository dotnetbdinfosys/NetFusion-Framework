<?php

declare(strict_types=1);

namespace NetFusion\Core\AI\Agent\Execution;

use NetFusion\Core\AI\Agent\Tools\ParameterType;
use Throwable;

class SandboxedToolDispatcher
{
    public function __construct(
        private readonly ToolRegistry $registry
    ) {
    }

    public function dispatch(ToolCall $call): ToolExecutionResult
    {
        $toolName = $call->getToolName();
        $tool = $this->registry->get($toolName);

        if ($tool === null) {
            return new ToolExecutionResult(
                toolCallId: $call->getId(),
                toolName: $toolName,
                result: null,
                isError: true,
                errorMessage: "Tool '{$toolName}' not found in registry"
            );
        }

        $definition = $tool->getDefinition();
        $args = $call->getArguments();

        // Validate required arguments
        foreach ($definition->getParameters() as $paramName => $param) {
            if ($param->isRequired() && !array_key_exists($paramName, $args)) {
                return new ToolExecutionResult(
                    toolCallId: $call->getId(),
                    toolName: $toolName,
                    result: null,
                    isError: true,
                    errorMessage: "Missing required parameter '{$paramName}' for tool '{$toolName}'"
                );
            }
        }

        // Type casting / normalization
        $normalizedArgs = [];
        foreach ($args as $key => $val) {
            $param = $definition->getParameter($key);
            if ($param !== null) {
                $normalizedArgs[$key] = $this->coerceValue($val, $param->getType());
            } else {
                $normalizedArgs[$key] = $val;
            }
        }

        $start = microtime(true);
        try {
            $output = $tool->execute($normalizedArgs);
            $duration = (microtime(true) - $start) * 1000.0;

            return new ToolExecutionResult(
                toolCallId: $call->getId(),
                toolName: $toolName,
                result: $output,
                isError: false,
                durationMs: round($duration, 3)
            );
        } catch (Throwable $e) {
            $duration = (microtime(true) - $start) * 1000.0;

            return new ToolExecutionResult(
                toolCallId: $call->getId(),
                toolName: $toolName,
                result: null,
                isError: true,
                durationMs: round($duration, 3),
                errorMessage: $e->getMessage()
            );
        }
    }

    /**
     * @param ToolCall[] $calls
     * @return array<string, ToolExecutionResult>
     */
    public function dispatchBatch(array $calls): array
    {
        $results = [];
        foreach ($calls as $call) {
            $results[$call->getId()] = $this->dispatch($call);
        }
        return $results;
    }

    private function coerceValue(mixed $val, ParameterType $type): mixed
    {
        return match ($type) {
            ParameterType::INTEGER => is_numeric($val) ? (int) $val : $val,
            ParameterType::NUMBER => is_numeric($val) ? (float) $val : $val,
            ParameterType::BOOLEAN => is_string($val)
                ? in_array(strtolower($val), ['true', '1', 'yes'], true)
                : (bool) $val,
            ParameterType::STRING => is_scalar($val) ? (string) $val : $val,
            ParameterType::ARRAY => is_array($val) ? $val : [$val],
            ParameterType::OBJECT => (array) $val,
        };
    }
}
