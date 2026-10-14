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

namespace NetFusion\Core\Workflow\Execution;

class ActivityExecution
{
    public const STATUS_SUCCESS = 'SUCCESS';
    public const STATUS_ERROR = 'ERROR';
    public const STATUS_SUSPENDED = 'SUSPENDED';
    public const STATUS_COMPENSATED = 'COMPENSATED';

    protected string $id;
    protected string $instanceId;
    protected string $nodeId;
    protected string $nodeType;
    protected string $action;
    protected array $input;
    protected array $output;
    protected string $status;
    protected float $durationMs;
    protected ?string $errorMessage;
    protected string $createdAt;

    public function __construct(
        string $id,
        string $instanceId,
        string $nodeId,
        string $nodeType,
        string $action,
        array $input = [],
        array $output = [],
        string $status = self::STATUS_SUCCESS,
        float $durationMs = 0.0,
        ?string $errorMessage = null,
        ?string $createdAt = null
    ) {
        $this->id = $id;
        $this->instanceId = $instanceId;
        $this->nodeId = $nodeId;
        $this->nodeType = $nodeType;
        $this->action = $action;
        $this->input = $input;
        $this->output = $output;
        $this->status = $status;
        $this->durationMs = $durationMs;
        $this->errorMessage = $errorMessage;
        $this->createdAt = $createdAt ?? date('Y-m-d H:i:s');
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getInstanceId(): string
    {
        return $this->instanceId;
    }

    public function getNodeId(): string
    {
        return $this->nodeId;
    }

    public function getNodeType(): string
    {
        return $this->nodeType;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getInput(): array
    {
        return $this->input;
    }

    public function getOutput(): array
    {
        return $this->output;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getDurationMs(): float
    {
        return $this->durationMs;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'instanceId' => $this->instanceId,
            'nodeId' => $this->nodeId,
            'nodeType' => $this->nodeType,
            'action' => $this->action,
            'input' => $this->input,
            'output' => $this->output,
            'status' => $this->status,
            'durationMs' => $this->durationMs,
            'errorMessage' => $this->errorMessage,
            'createdAt' => $this->createdAt,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'] ?? uniqid('act_', true),
            $data['instanceId'] ?? '',
            $data['nodeId'] ?? '',
            $data['nodeType'] ?? '',
            $data['action'] ?? 'EXECUTE',
            $data['input'] ?? [],
            $data['output'] ?? [],
            $data['status'] ?? self::STATUS_SUCCESS,
            (float) ($data['durationMs'] ?? 0.0),
            $data['errorMessage'] ?? null,
            $data['createdAt'] ?? date('Y-m-d H:i:s')
        );
    }
}
