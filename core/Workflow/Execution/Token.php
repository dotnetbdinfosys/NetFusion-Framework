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

class Token
{
    public const STATE_ACTIVE = 'ACTIVE';
    public const STATE_WAITING = 'WAITING';
    public const STATE_COMPLETED = 'COMPLETED';
    public const STATE_CANCELLED = 'CANCELLED';

    protected string $id;
    protected string $nodeId;
    protected string $state;
    protected ?string $parentTokenId;
    protected ?string $branchKey;
    protected float $createdAt;

    public function __construct(
        string $id,
        string $nodeId,
        string $state = self::STATE_ACTIVE,
        ?string $parentTokenId = null,
        ?string $branchKey = null,
        ?float $createdAt = null
    ) {
        $this->id = $id;
        $this->nodeId = $nodeId;
        $this->state = $state;
        $this->parentTokenId = $parentTokenId;
        $this->branchKey = $branchKey;
        $this->createdAt = $createdAt ?? microtime(true);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getNodeId(): string
    {
        return $this->nodeId;
    }

    public function setNodeId(string $nodeId): self
    {
        $this->nodeId = $nodeId;
        return $this;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function setState(string $state): self
    {
        $this->state = $state;
        return $this;
    }

    public function getParentTokenId(): ?string
    {
        return $this->parentTokenId;
    }

    public function getBranchKey(): ?string
    {
        return $this->branchKey;
    }

    public function isActive(): bool
    {
        return $this->state === self::STATE_ACTIVE;
    }

    public function isWaiting(): bool
    {
        return $this->state === self::STATE_WAITING;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'nodeId' => $this->nodeId,
            'state' => $this->state,
            'parentTokenId' => $this->parentTokenId,
            'branchKey' => $this->branchKey,
            'createdAt' => $this->createdAt,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'] ?? uniqid('tok_', true),
            $data['nodeId'] ?? '',
            $data['state'] ?? self::STATE_ACTIVE,
            $data['parentTokenId'] ?? null,
            $data['branchKey'] ?? null,
            (float) ($data['createdAt'] ?? microtime(true))
        );
    }
}
