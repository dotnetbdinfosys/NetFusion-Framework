<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\Consul\Models;

use NetFusion\Core\Consul\Enums\CheckStatus;
use NetFusion\Core\Consul\Enums\CheckType;

/**
 * Health check definition associated with a service instance or node.
 */
class ServiceCheck
{
    protected string $id;
    protected string $name;
    protected CheckType $type;
    protected CheckStatus $status;
    protected ?string $httpUrl;
    protected ?string $tcpAddress;
    protected int $interval; // seconds
    protected int $timeout; // seconds
    protected int $deregisterCriticalServiceAfter; // seconds
    protected string $output;
    protected ?string $notes;
    protected float $lastCheckedAt;
    /** @var callable|null */
    protected $scriptCallback;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        string $id,
        string $name,
        CheckType $type,
        CheckStatus $status = CheckStatus::PASSING,
        array $options = []
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->type = $type;
        $this->status = $status;
        $this->httpUrl = $options['httpUrl'] ?? $options['http'] ?? null;
        $this->tcpAddress = $options['tcpAddress'] ?? $options['tcp'] ?? null;
        $this->interval = (int)($options['interval'] ?? 10);
        $this->timeout = (int)($options['timeout'] ?? 5);
        $this->deregisterCriticalServiceAfter = (int)($options['deregisterCriticalServiceAfter'] ?? 30);
        $this->output = (string)($options['output'] ?? '');
        $this->notes = $options['notes'] ?? null;
        $this->scriptCallback = $options['scriptCallback'] ?? null;
        $this->lastCheckedAt = (float)($options['lastCheckedAt'] ?? microtime(true));
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): CheckType
    {
        return $this->type;
    }

    public function getStatus(): CheckStatus
    {
        return $this->status;
    }

    public function setStatus(CheckStatus $status, string $output = ''): self
    {
        $this->status = $status;
        if ($output !== '') {
            $this->output = $output;
        }
        $this->lastCheckedAt = microtime(true);
        return $this;
    }

    public function getHttpUrl(): ?string
    {
        return $this->httpUrl;
    }

    public function getTcpAddress(): ?string
    {
        return $this->tcpAddress;
    }

    public function getInterval(): int
    {
        return $this->interval;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function getDeregisterCriticalServiceAfter(): int
    {
        return $this->deregisterCriticalServiceAfter;
    }

    public function getOutput(): string
    {
        return $this->output;
    }

    public function setOutput(string $output): self
    {
        $this->output = $output;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function getLastCheckedAt(): float
    {
        return $this->lastCheckedAt;
    }

    public function setLastCheckedAt(float $timestamp): self
    {
        $this->lastCheckedAt = $timestamp;
        return $this;
    }

    public function getScriptCallback(): ?callable
    {
        return $this->scriptCallback;
    }

    public function setScriptCallback(?callable $callback): self
    {
        $this->scriptCallback = $callback;
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'httpUrl' => $this->httpUrl,
            'tcpAddress' => $this->tcpAddress,
            'interval' => $this->interval,
            'timeout' => $this->timeout,
            'deregisterCriticalServiceAfter' => $this->deregisterCriticalServiceAfter,
            'output' => $this->output,
            'notes' => $this->notes,
            'lastCheckedAt' => $this->lastCheckedAt,
        ];
    }
}
