<?php

declare(strict_types=1);

namespace NetFusion\Core\AI\Agent\Tools;

class ToolParameter
{
    /**
     * @param string $name
     * @param ParameterType $type
     * @param string $description
     * @param bool $required
     * @param array<string|int|float>|null $enum
     * @param mixed $default
     */
    public function __construct(
        private readonly string $name,
        private readonly ParameterType $type,
        private readonly string $description = '',
        private readonly bool $required = true,
        private readonly ?array $enum = null,
        private readonly mixed $default = null
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): ParameterType
    {
        return $this->type;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function getEnum(): ?array
    {
        return $this->enum;
    }

    public function getDefault(): mixed
    {
        return $this->default;
    }

    public function toJsonSchema(): array
    {
        $schema = [
            'type' => $this->type->value,
            'description' => $this->description,
        ];

        if ($this->enum !== null && !empty($this->enum)) {
            $schema['enum'] = $this->enum;
        }

        if ($this->default !== null) {
            $schema['default'] = $this->default;
        }

        return $schema;
    }
}
