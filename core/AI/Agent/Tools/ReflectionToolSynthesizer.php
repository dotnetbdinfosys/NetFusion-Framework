<?php

declare(strict_types=1);

namespace NetFusion\Core\AI\Agent\Tools;

use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

class ReflectionToolSynthesizer
{
    /**
     * Synthesize a tool from a specific method of an object instance.
     */
    public function synthesizeMethod(object $instance, string $methodName, ?string $toolName = null): ToolInterface
    {
        $refClass = new ReflectionClass($instance);
        $refMethod = $refClass->getMethod($methodName);

        $name = $toolName ?? $methodName;
        $doc = (string) $refMethod->getDocComment();

        $description = $this->parseMethodDescription($doc) ?: "Execute {$methodName}";
        $paramDocs = $this->parseParamDescriptions($doc);

        $parameters = [];
        foreach ($refMethod->getParameters() as $refParam) {
            $paramName = $refParam->getName();
            $paramType = $this->mapReflectionType($refParam->getType());
            $paramDesc = $paramDocs[$paramName] ?? "Parameter {$paramName}";
            $isRequired = !$refParam->isOptional();
            $default = $refParam->isDefaultValueAvailable() ? $refParam->getDefaultValue() : null;

            $parameters[] = new ToolParameter(
                name: $paramName,
                type: $paramType,
                description: $paramDesc,
                required: $isRequired,
                default: $default
            );
        }

        return new CallableTool(
            name: $name,
            description: $description,
            handler: function (array $args) use ($instance, $refMethod) {
                $orderedArgs = [];
                foreach ($refMethod->getParameters() as $p) {
                    $pName = $p->getName();
                    if (array_key_exists($pName, $args)) {
                        $orderedArgs[] = $args[$pName];
                    } elseif ($p->isDefaultValueAvailable()) {
                        $orderedArgs[] = $p->getDefaultValue();
                    } else {
                        $orderedArgs[] = null;
                    }
                }
                return $refMethod->invokeArgs($instance, $orderedArgs);
            },
            parameters: $parameters
        );
    }

    /**
     * Synthesize tools for all public methods of a class instance (excluding magic methods).
     *
     * @return ToolInterface[]
     */
    public function synthesizeClass(object $instance, ?array $methodNames = null): array
    {
        $refClass = new ReflectionClass($instance);
        $tools = [];

        foreach ($refClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (str_starts_with($method->getName(), '__')) {
                continue;
            }
            if ($methodNames !== null && !in_array($method->getName(), $methodNames, true)) {
                continue;
            }

            $tools[] = $this->synthesizeMethod($instance, $method->getName());
        }

        return $tools;
    }

    /**
     * Synthesize tools from a class string or object instance with an optional prefix.
     *
     * @param object|string $target
     * @param string|null $prefix
     * @return array<string, ToolInterface>
     */
    public function synthesizeFromClass(object|string $target, ?string $prefix = null): array
    {
        $instance = is_string($target) ? new $target() : $target;
        $refClass = new ReflectionClass($instance);
        $tools = [];

        foreach ($refClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (str_starts_with($method->getName(), '__')) {
                continue;
            }

            $toolName = $prefix !== null ? $prefix . '_' . $method->getName() : $method->getName();
            $tool = $this->synthesizeMethod($instance, $method->getName(), $toolName);
            $tools[$toolName] = $tool;
        }

        return $tools;
    }

    private function mapReflectionType(?\ReflectionType $type): ParameterType
    {
        if ($type instanceof ReflectionNamedType) {
            return match ($type->getName()) {
                'int' => ParameterType::INTEGER,
                'float' => ParameterType::NUMBER,
                'bool' => ParameterType::BOOLEAN,
                'array' => ParameterType::ARRAY,
                'object' => ParameterType::OBJECT,
                default => ParameterType::STRING,
            };
        }

        return ParameterType::STRING;
    }

    private function parseMethodDescription(string $doc): string
    {
        $lines = explode("\n", $doc);
        $descLines = [];

        foreach ($lines as $line) {
            $line = trim($line, "/* \t\r\n");
            if ($line === '' || str_starts_with($line, '@')) {
                if (!empty($descLines) && str_starts_with($line, '@')) {
                    break;
                }
                continue;
            }
            $descLines[] = $line;
        }

        return implode(' ', $descLines);
    }

    /**
     * @return array<string, string>
     */
    private function parseParamDescriptions(string $doc): array
    {
        $paramDocs = [];
        $pattern = '/@param\s+(?:[^\s]+)\s+\$([a-zA-Z0-9_]+)(?:\s+(.*))?/';

        if (preg_match_all($pattern, $doc, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $name = $match[1];
                $desc = trim($match[2] ?? '');
                $paramDocs[$name] = $desc;
            }
        }

        return $paramDocs;
    }
}
