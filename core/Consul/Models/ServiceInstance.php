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

/**
 * Represents a registered service instance in the Consul catalog.
 */
class ServiceInstance
{
    protected string $id;
    protected string $name;
    protected string $address;
    protected int $port;
    /** @var array<string> */
    protected array $tags;
    /** @var array<string, string> */
    protected array $meta;
    /** @var array{passing: int, warning: int} */
    protected array $weights;
    /** @var array<string, ServiceCheck> */
    protected array $checks = [];
    protected CheckStatus $status = CheckStatus::PASSING;
    protected float $registeredAt;

    /**
     * @param array<string> $tags
     * @param array<string, string> $meta
     * @param array{passing?: int, warning?: int} $weights
     * @param array<ServiceCheck> $checks
     */
    public function __construct(
        string $id,
        string $name,
        string $address,
        int $port,
        array $tags = [],
        array $meta = [],
        array $weights = ['passing' => 1, 'warning' => 1],
        array $checks = []
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->address = $address;
        $this->port = $port;
        $this->tags = array_values(array_unique($tags));
        $this->meta = $meta;
        $this->weights = [
            'passing' => $weights['passing'] ?? 1,
            'warning' => $weights['warning'] ?? 1,
        ];
        $this->registeredAt = microtime(true);

        foreach ($checks as $check) {
            $this->addCheck($check);
        }
        $this->recomputeStatus();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * @return array<string>
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags, true);
    }

    /**
     * @return array<string, string>
     */
    public function getMeta(): array
    {
        return $this->meta;
    }

    public function getMetaValue(string $key, ?string $default = null): ?string
    {
        return $this->meta[$key] ?? $default;
    }

    /**
     * @return array{passing: int, warning: int}
     */
    public function getWeights(): array
    {
        return $this->weights;
    }

    public function getEffectiveWeight(): int
    {
        if ($this->status === CheckStatus::CRITICAL) {
            return 0;
        }
        if ($this->status === CheckStatus::WARNING) {
            return max(1, $this->weights['warning']);
        }
        return max(1, $this->weights['passing']);
    }

    /**
     * @return array<string, ServiceCheck>
     */
    public function getChecks(): array
    {
        return $this->checks;
    }

    public function getCheck(string $checkId): ?ServiceCheck
    {
        return $this->checks[$checkId] ?? null;
    }

    public function addCheck(ServiceCheck $check): self
    {
        $this->checks[$check->getId()] = $check;
        $this->recomputeStatus();
        return $this;
    }

    public function removeCheck(string $checkId): self
    {
        unset($this->checks[$checkId]);
        $this->recomputeStatus();
        return $this;
    }

    public function getStatus(): CheckStatus
    {
        return $this->status;
    }

    public function isHealthy(): bool
    {
        return $this->status === CheckStatus::PASSING || $this->status === CheckStatus::WARNING;
    }

    public function isPassing(): bool
    {
        return $this->status === CheckStatus::PASSING;
    }

    public function recomputeStatus(): CheckStatus
    {
        if (empty($this->checks)) {
            $this->status = CheckStatus::PASSING;
            return $this->status;
        }

        $hasCritical = false;
        $hasWarning = false;

        foreach ($this->checks as $check) {
            if ($check->getStatus() === CheckStatus::CRITICAL) {
                $hasCritical = true;
                break;
            }
            if ($check->getStatus() === CheckStatus::WARNING) {
                $hasWarning = true;
            }
        }

        if ($hasCritical) {
            $this->status = CheckStatus::CRITICAL;
        } elseif ($hasWarning) {
            $this->status = CheckStatus::WARNING;
        } else {
            $this->status = CheckStatus::PASSING;
        }

        return $this->status;
    }

    public function getRegisteredAt(): float
    {
        return $this->registeredAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $checksArray = [];
        foreach ($this->checks as $id => $check) {
            $checksArray[$id] = $check->toArray();
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'port' => $this->port,
            'tags' => $this->tags,
            'meta' => $this->meta,
            'weights' => $this->weights,
            'status' => $this->status->value,
            'registeredAt' => $this->registeredAt,
            'checks' => $checksArray,
        ];
    }
}
