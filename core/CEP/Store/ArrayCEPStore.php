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

namespace NetFusion\Core\CEP\Store;

use NetFusion\Core\CEP\Contracts\CEPStoreInterface;

class ArrayCEPStore implements CEPStoreInterface
{
    /** @var array<string, array<string, mixed>> */
    protected array $alerts = [];
    /** @var array<string, array<string, mixed>> */
    protected array $states = [];
    /** @var array<array{metric: string, value: float, tags: array<string, mixed>, timestamp: float}> */
    protected array $metrics = [];

    public function saveAlert(array $alertData): string
    {
        $id = $alertData['id'] ?? self::generateUuid();
        $alertData['id'] = $id;
        $alertData['created_at'] = $alertData['created_at'] ?? microtime(true);
        $this->alerts[$id] = $alertData;
        return $id;
    }

    public function getAlerts(array $filter = [], int $limit = 50, int $offset = 0): array
    {
        $results = array_values($this->alerts);

        if (!empty($filter)) {
            $results = array_filter($results, function ($alert) use ($filter) {
                foreach ($filter as $key => $val) {
                    if (!isset($alert[$key]) || $alert[$key] !== $val) {
                        return false;
                    }
                }
                return true;
            });
        }

        return array_slice($results, $offset, $limit);
    }

    public function saveState(string $ruleName, string $partitionKey, array $stateData): void
    {
        $key = sprintf('%s::%s', $ruleName, $partitionKey);
        $this->states[$key] = [
            'rule_name' => $ruleName,
            'partition_key' => $partitionKey,
            'state_data' => $stateData,
            'updated_at' => microtime(true),
        ];
    }

    public function loadState(string $ruleName, string $partitionKey): ?array
    {
        $key = sprintf('%s::%s', $ruleName, $partitionKey);
        return $this->states[$key]['state_data'] ?? null;
    }

    public function clearState(?string $ruleName = null, ?string $partitionKey = null): void
    {
        if ($ruleName === null && $partitionKey === null) {
            $this->states = [];
            return;
        }

        foreach ($this->states as $k => $state) {
            if ($ruleName !== null && $state['rule_name'] !== $ruleName) {
                continue;
            }
            if ($partitionKey !== null && $state['partition_key'] !== $partitionKey) {
                continue;
            }
            unset($this->states[$k]);
        }
    }

    public function recordMetric(string $metricName, float $value, array $tags = []): void
    {
        $this->metrics[] = [
            'metric' => $metricName,
            'value' => $value,
            'tags' => $tags,
            'timestamp' => microtime(true),
        ];
    }

    public function getMetrics(): array
    {
        return $this->metrics;
    }

    public function clear(): void
    {
        $this->alerts = [];
        $this->states = [];
        $this->metrics = [];
    }

    protected static function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
