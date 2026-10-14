<?php

declare(strict_types=1);

namespace NetFusion\Core\AI\Agent\Tools;

class ToolDefinition
{
    /** @var array<string, ToolParameter> */
    private array $parameters = [];

    /**
     * @param string $name
     * @param string $description
     * @param ToolParameter[] $parameters
     * @param string $returnsDescription
     */
    public function __construct(
        private readonly string $name,
        private readonly string $description,
        array $parameters = [],
        private readonly string $returnsDescription = ''
    ) {
        foreach ($parameters as $param) {
            $this->parameters[$param->getName()] = $param;
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return array<string, ToolParameter>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getParameter(string $name): ?ToolParameter
    {
        return $this->parameters[$name] ?? null;
    }

    public function getReturnsDescription(): string
    {
        return $this->returnsDescription;
    }

    /**
     * Generate standard OpenAI function calling JSON schema.
     *
     * @return array<string, mixed>
     */
    public function toOpenAiSchema(): array
    {
        return $this->toJsonSchema();
    }

    /**
     * Generate Google Gemini function declaration format.
     *
     * @return array<string, mixed>
     */
    public function toGeminiSchema(): array
    {
        $properties = [];
        $required = [];

        foreach ($this->parameters as $name => $param) {
            $properties[$name] = $param->toJsonSchema();
            if ($param->isRequired()) {
                $required[] = $name;
            }
        }

        return [
            'name' => $this->name,
            'description' => $this->description,
            'parameters' => [
                'type' => 'OBJECT',
                'properties' => $properties,
                'required' => $required,
            ],
        ];
    }

    /**
     * Generate Anthropic Claude tool definition schema.
     *
     * @return array<string, mixed>
     */
    public function toAnthropicSchema(): array
    {
        $properties = [];
        $required = [];

        foreach ($this->parameters as $name => $param) {
            $properties[$name] = $param->toJsonSchema();
            if ($param->isRequired()) {
                $required[] = $name;
            }
        }

        return [
            'name' => $this->name,
            'description' => $this->description,
            'input_schema' => [
                'type' => 'object',
                'properties' => $properties,
                'required' => $required,
            ],
        ];
    }

    /**
     * Generate standard OpenAI/Gemini/Anthropic function calling JSON schema.
     *
     * @return array<string, mixed>
     */
    public function toJsonSchema(): array
    {
        $properties = [];
        $required = [];

        foreach ($this->parameters as $name => $param) {
            $properties[$name] = $param->toJsonSchema();
            if ($param->isRequired()) {
                $required[] = $name;
            }
        }

        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'description' => $this->description,
                'parameters' => [
                    'type' => 'object',
                    'properties' => $properties,
                    'required' => $required,
                ],
            ],
        ];
    }
}
