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

abstract class AbstractNode
{
    protected string $id;
    protected string $name;
    protected string $type;
    /** @var array<string> */
    protected array $incomingFlows = [];
    /** @var array<string> */
    protected array $outgoingFlows = [];
    /** @var array<string, mixed> */
    protected array $metadata = [];

    public function __construct(string $id, string $name, string $type, array $metadata = [])
    {
        $this->id = $id;
        $this->name = $name;
        $this->type = $type;
        $this->metadata = $metadata;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getIncomingFlows(): array
    {
        return $this->incomingFlows;
    }

    public function addIncomingFlow(string $flowId): self
    {
        if (!in_array($flowId, $this->incomingFlows, true)) {
            $this->incomingFlows[] = $flowId;
        }
        return $this;
    }

    public function getOutgoingFlows(): array
    {
        return $this->outgoingFlows;
    }

    public function addOutgoingFlow(string $flowId): self
    {
        if (!in_array($flowId, $this->outgoingFlows, true)) {
            $this->outgoingFlows[] = $flowId;
        }
        return $this;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    abstract public function toArray(): array;
}
