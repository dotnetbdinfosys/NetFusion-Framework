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

namespace NetFusion\Core\Workflow\Definition\Nodes;

class ServiceTask extends AbstractNode
{
    /** @var string|callable */
    protected mixed $handler;

    /** @var string|callable|null */
    protected mixed $compensationHandler;

    protected int $maxRetries;
    protected int $retryDelayMs;

    public function __construct(
        string $id,
        string $name,
        mixed $handler,
        mixed $compensationHandler = null,
        int $maxRetries = 0,
        int $retryDelayMs = 0,
        array $metadata = []
    ) {
        parent::__construct($id, $name, 'serviceTask', $metadata);
        $this->handler = $handler;
        $this->compensationHandler = $compensationHandler;
        $this->maxRetries = $maxRetries;
        $this->retryDelayMs = $retryDelayMs;
    }

    public function getHandler(): mixed
    {
        return $this->handler;
    }

    public function getCompensationHandler(): mixed
    {
        return $this->compensationHandler;
    }

    public function setCompensationHandler(mixed $handler): self
    {
        $this->compensationHandler = $handler;
        return $this;
    }

    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    public function getRetryDelayMs(): int
    {
        return $this->retryDelayMs;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'handler' => is_string($this->handler) ? $this->handler : (is_object($this->handler) ? get_class($this->handler) : 'Closure'),
            'compensationHandler' => is_string($this->compensationHandler) ? $this->compensationHandler : ($this->compensationHandler !== null ? 'Closure' : null),
            'maxRetries' => $this->maxRetries,
            'retryDelayMs' => $this->retryDelayMs,
            'incomingFlows' => $this->incomingFlows,
            'outgoingFlows' => $this->outgoingFlows,
            'metadata' => $this->metadata,
        ];
    }

    public static function fromArray(array $data): self
    {
        $node = new self(
            $data['id'],
            $data['name'] ?? 'Service Task',
            $data['handler'] ?? '',
            $data['compensationHandler'] ?? null,
            (int) ($data['maxRetries'] ?? 0),
            (int) ($data['retryDelayMs'] ?? 0),
            $data['metadata'] ?? []
        );
        $node->incomingFlows = $data['incomingFlows'] ?? [];
        $node->outgoingFlows = $data['outgoingFlows'] ?? [];
        return $node;
    }
}
