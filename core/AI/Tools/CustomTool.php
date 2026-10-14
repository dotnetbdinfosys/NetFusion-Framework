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

use Closure;
use NetFusion\Core\AI\Contracts\ToolInterface;

class CustomTool implements ToolInterface
{
    /** @var callable */
    protected $handler;

    /**
     * @param string $name
     * @param string $description
     * @param array<string, mixed> $parametersSchema
     * @param callable $handler
     */
    public function __construct(
        protected string $name,
        protected string $description,
        protected array $parametersSchema,
        callable $handler
    ) {
        $this->handler = $handler;
    }

    public static function make(
        string $name,
        string $description,
        array $parametersSchema,
        callable $handler
    ): self {
        return new self($name, $description, $parametersSchema, $handler);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getParametersSchema(): array
    {
        return $this->parametersSchema;
    }

    public function execute(array $arguments): mixed
    {
        return ($this->handler)($arguments);
    }

    public function toFunctionDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'description' => $this->description,
                'parameters' => $this->parametersSchema,
            ],
        ];
    }
}
