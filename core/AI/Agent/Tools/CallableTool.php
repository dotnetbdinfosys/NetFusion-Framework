<?php

declare(strict_types=1);

namespace NetFusion\Core\AI\Agent\Tools;

use Closure;

class CallableTool implements ToolInterface
{
    private ToolDefinition $definition;
    private Closure $handler;

    /**
     * @param string $name
     * @param string $description
     * @param callable $handler
     * @param ToolParameter[] $parameters
     * @param string $returnsDescription
     */
    public function __construct(
        private readonly string $name,
        private readonly string $description,
        callable $handler,
        array $parameters = [],
        string $returnsDescription = ''
    ) {
        $this->handler = $handler instanceof Closure ? $handler : Closure::fromCallable($handler);
        $this->definition = new ToolDefinition($name, $description, $parameters, $returnsDescription);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getDefinition(): ToolDefinition
    {
        return $this->definition;
    }

    public function execute(array $arguments): mixed
    {
        try {
            $ref = is_array($this->handler)
                ? new \ReflectionMethod($this->handler[0], $this->handler[1])
                : new \ReflectionFunction($this->handler);

            $refParams = $ref->getParameters();
            if (count($refParams) === 1 && ($refParams[0]->getName() === 'arguments' || ($refParams[0]->getType() && $refParams[0]->getType()->getName() === 'array'))) {
                return ($this->handler)($arguments);
            }

            $orderedArgs = [];
            foreach ($refParams as $param) {
                $name = $param->getName();
                if (array_key_exists($name, $arguments)) {
                    $orderedArgs[] = $arguments[$name];
                } elseif ($param->isDefaultValueAvailable()) {
                    $orderedArgs[] = $param->getDefaultValue();
                } else {
                    $orderedArgs[] = null;
                }
            }

            return ($this->handler)(...$orderedArgs);
        } catch (\Throwable $e) {
            // Fallback to direct invocation if reflection cannot resolve
            return ($this->handler)($arguments);
        }
    }
}
