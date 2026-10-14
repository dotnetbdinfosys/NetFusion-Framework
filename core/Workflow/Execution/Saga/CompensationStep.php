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

namespace NetFusion\Core\Workflow\Execution\Saga;

class CompensationStep
{
    protected string $nodeId;
    /** @var string|callable */
    protected mixed $handler;
    protected array $inputSnapshot;
    protected string $registeredAt;

    public function __construct(
        string $nodeId,
        mixed $handler,
        array $inputSnapshot = [],
        ?string $registeredAt = null
    ) {
        $this->nodeId = $nodeId;
        $this->handler = $handler;
        $this->inputSnapshot = $inputSnapshot;
        $this->registeredAt = $registeredAt ?? date('Y-m-d H:i:s');
    }

    public function getNodeId(): string
    {
        return $this->nodeId;
    }

    public function getHandler(): mixed
    {
        return $this->handler;
    }

    public function getInputSnapshot(): array
    {
        return $this->inputSnapshot;
    }

    public function getRegisteredAt(): string
    {
        return $this->registeredAt;
    }

    public function toArray(): array
    {
        return [
            'nodeId' => $this->nodeId,
            'handler' => is_string($this->handler) ? $this->handler : (is_object($this->handler) ? get_class($this->handler) : 'Closure'),
            'inputSnapshot' => $this->inputSnapshot,
            'registeredAt' => $this->registeredAt,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['nodeId'],
            $data['handler'],
            $data['inputSnapshot'] ?? [],
            $data['registeredAt'] ?? null
        );
    }
}
