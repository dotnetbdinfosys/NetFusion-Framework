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

use NetFusion\Core\Workflow\Contracts\WorkflowInstanceInterface;
use NetFusion\Core\Workflow\Execution\Saga\CompensationStep;

class ProcessInstance implements WorkflowInstanceInterface
{
    protected string $id;
    protected string $definitionId;
    protected ?string $businessKey;
    protected string $status;
    /** @var array<string, mixed> */
    protected array $variables;
    /** @var array<string, Token> */
    protected array $tokens = [];
    /** @var array<CompensationStep> */
    protected array $compensationStack = [];
    /** @var array<ActivityExecution> */
    protected array $history = [];
    protected string $createdAt;
    protected string $updatedAt;
    protected ?string $completedAt = null;

    public function __construct(
        string $id,
        string $definitionId,
        ?string $businessKey = null,
        string $status = self::STATUS_PENDING,
        array $variables = [],
        ?string $createdAt = null,
        ?string $updatedAt = null,
        ?string $completedAt = null
    ) {
        $this->id = $id;
        $this->definitionId = $definitionId;
        $this->businessKey = $businessKey;
        $this->status = $status;
        $this->variables = $variables;
        $this->createdAt = $createdAt ?? date('Y-m-d H:i:s');
        $this->updatedAt = $updatedAt ?? date('Y-m-d H:i:s');
        $this->completedAt = $completedAt;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getDefinitionId(): string
    {
        return $this->definitionId;
    }

    public function getBusinessKey(): ?string
    {
        return $this->businessKey;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        $this->updatedAt = date('Y-m-d H:i:s');
        if (in_array($status, [self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED, self::STATUS_COMPENSATED], true)) {
            $this->completedAt = date('Y-m-d H:i:s');
        }
        return $this;
    }

    public function getVariables(): array
    {
        return $this->variables;
    }

    public function getVariable(string $key, mixed $default = null): mixed
    {
        return $this->variables[$key] ?? $default;
    }

    public function setVariable(string $key, mixed $value): self
    {
        $this->variables[$key] = $value;
        $this->updatedAt = date('Y-m-d H:i:s');
        return $this;
    }

    public function mergeVariables(array $vars): self
    {
        $this->variables = array_merge($this->variables, $vars);
        $this->updatedAt = date('Y-m-d H:i:s');
        return $this;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function addToken(Token $token): self
    {
        $this->tokens[$token->getId()] = $token;
        $this->updatedAt = date('Y-m-d H:i:s');
        return $this;
    }

    public function removeToken(string $tokenId): self
    {
        unset($this->tokens[$tokenId]);
        $this->updatedAt = date('Y-m-d H:i:s');
        return $this;
    }

    public function getToken(string $tokenId): ?Token
    {
        return $this->tokens[$tokenId] ?? null;
    }

    /**
     * @return array<string, Token>
     */
    public function getTokens(): array
    {
        return $this->tokens;
    }

    /**
     * @return array<Token>
     */
    public function getActiveTokens(): array
    {
        return array_values(array_filter($this->tokens, fn (Token $t) => $t->isActive()));
    }

    /**
     * @return array<Token>
     */
    public function getWaitingTokens(): array
    {
        return array_values(array_filter($this->tokens, fn (Token $t) => $t->isWaiting()));
    }

    public function addCompensationStep(CompensationStep $step): self
    {
        $this->compensationStack[] = $step;
        return $this;
    }

    /**
     * @return array<CompensationStep>
     */
    public function getCompensationStack(): array
    {
        return $this->compensationStack;
    }

    public function addHistory(ActivityExecution $act): self
    {
        $this->history[] = $act;
        return $this;
    }

    /**
     * @return array<ActivityExecution>
     */
    public function getHistory(): array
    {
        return $this->history;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): string
    {
        return $this->updatedAt;
    }

    public function getCompletedAt(): ?string
    {
        return $this->completedAt;
    }

    public function toArray(): array
    {
        $tokensArray = [];
        foreach ($this->tokens as $id => $token) {
            $tokensArray[$id] = $token->toArray();
        }

        $compensationArray = [];
        foreach ($this->compensationStack as $step) {
            $compensationArray[] = $step->toArray();
        }

        $historyArray = [];
        foreach ($this->history as $hist) {
            $historyArray[] = $hist->toArray();
        }

        return [
            'id' => $this->id,
            'definitionId' => $this->definitionId,
            'businessKey' => $this->businessKey,
            'status' => $this->status,
            'variables' => $this->variables,
            'tokens' => $tokensArray,
            'compensationStack' => $compensationArray,
            'history' => $historyArray,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'completedAt' => $this->completedAt,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public static function fromArray(array $data): self
    {
        $instance = new self(
            $data['id'],
            $data['definitionId'] ?? '',
            $data['businessKey'] ?? null,
            $data['status'] ?? self::STATUS_PENDING,
            $data['variables'] ?? [],
            $data['createdAt'] ?? null,
            $data['updatedAt'] ?? null,
            $data['completedAt'] ?? null
        );

        foreach ($data['tokens'] ?? [] as $tokenData) {
            $token = Token::fromArray($tokenData);
            $instance->addToken($token);
        }

        foreach ($data['compensationStack'] ?? [] as $stepData) {
            $step = CompensationStep::fromArray($stepData);
            $instance->addCompensationStep($step);
        }

        foreach ($data['history'] ?? [] as $histData) {
            $instance->addHistory(ActivityExecution::fromArray($histData));
        }

        return $instance;
    }
}
