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

namespace NetFusion\Core\WAF\Store;

use NetFusion\Core\WAF\Contracts\WAFStoreInterface;
use NetFusion\Core\WAF\IP\ThreatContext;

class ArrayWAFStore implements WAFStoreInterface
{
    /**
     * @var array<array<string, mixed>>
     */
    protected array $threatEvents = [];

    /**
     * @var array<string, array{reason: string, expires_at: int}>
     */
    protected array $ipBlocks = [];

    /**
     * @var array<string, int>
     */
    protected array $metrics = [
        'inspected' => 0,
        'passed' => 0,
        'blocked' => 0,
        'challenged' => 0,
    ];

    public function logThreatEvent(ThreatContext $context): void
    {
        $this->threatEvents[] = [
            'ip' => $context->getIp(),
            'method' => $context->getMethod(),
            'uri' => $context->getUri(),
            'action' => $context->getAction(),
            'reason' => $context->getBlockReason(),
            'rule_id' => $context->getTriggeredRuleId(),
            'anomaly_score' => $context->getAnomalyScore()->getScore(),
            'bot_type' => $context->getBotType(),
            'ja3_hash' => $context->getJa3Hash(),
            'timestamp' => $context->getTimestamp(),
        ];
    }

    public function getRecentThreatEvents(int $limit = 100): array
    {
        $events = array_reverse($this->threatEvents);
        return array_slice($events, 0, $limit);
    }

    public function saveIpBlock(string $ip, string $reason, int $expiresAt): void
    {
        $this->ipBlocks[$ip] = [
            'reason' => $reason,
            'expires_at' => $expiresAt,
        ];
    }

    public function removeIpBlock(string $ip): bool
    {
        if (isset($this->ipBlocks[$ip])) {
            unset($this->ipBlocks[$ip]);
            return true;
        }
        return false;
    }

    public function isIpBlocked(string $ip): bool
    {
        if (isset($this->ipBlocks[$ip])) {
            $item = $this->ipBlocks[$ip];
            if ($item['expires_at'] === 0 || $item['expires_at'] > time()) {
                return true;
            }
            unset($this->ipBlocks[$ip]);
        }
        return false;
    }

    public function getActiveIpBlocks(): array
    {
        $now = time();
        foreach ($this->ipBlocks as $ip => $item) {
            if ($item['expires_at'] !== 0 && $item['expires_at'] <= $now) {
                unset($this->ipBlocks[$ip]);
            }
        }
        return $this->ipBlocks;
    }

    public function recordMetric(string $metric, int $count = 1): void
    {
        $this->metrics[$metric] = ($this->metrics[$metric] ?? 0) + $count;
    }

    public function getMetrics(): array
    {
        return $this->metrics;
    }
}
