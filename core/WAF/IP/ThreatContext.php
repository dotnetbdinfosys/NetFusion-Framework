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

namespace NetFusion\Core\WAF\IP;

use NetFusion\Core\WAF\Detectors\AnomalyScore;
use JsonSerializable;

class ThreatContext implements JsonSerializable
{
    protected AnomalyScore $anomalyScore;
    protected string $action = 'ALLOW'; // ALLOW, BLOCK, CHALLENGE, LOG
    protected ?string $blockReason = null;
    protected ?string $triggeredRuleId = null;
    protected ?string $ja3Hash = null;
    protected ?string $botType = null;
    protected int $timestamp;

    /**
     * @param string $ip Client IP
     * @param string $method HTTP method
     * @param string $uri Request URI
     * @param array<string, mixed> $headers Request headers
     * @param array<string, mixed> $query Query parameters
     * @param array<string, mixed>|string $body Request payload
     * @param array<string, mixed> $cookies Request cookies
     * @param AnomalyScore|null $anomalyScore
     */
    public function __construct(
        protected string $ip,
        protected string $method,
        protected string $uri,
        protected array $headers = [],
        protected array $query = [],
        protected array|string $body = [],
        protected array $cookies = [],
        ?AnomalyScore $anomalyScore = null
    ) {
        $this->anomalyScore = $anomalyScore ?? new AnomalyScore();
        $this->timestamp = time();

        if (isset($headers['x-ja3-fingerprint']) || isset($headers['X-JA3-Fingerprint'])) {
            $this->ja3Hash = (string)($headers['x-ja3-fingerprint'] ?? $headers['X-JA3-Fingerprint']);
        }
    }

    public function getIp(): string
    {
        return $this->ip;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getQuery(): array
    {
        return $this->query;
    }

    public function getBody(): array|string
    {
        return $this->body;
    }

    public function getCookies(): array
    {
        return $this->cookies;
    }

    public function getAnomalyScore(): AnomalyScore
    {
        return $this->anomalyScore;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action, ?string $reason = null, ?string $ruleId = null): void
    {
        $this->action = strtoupper($action);
        $this->blockReason = $reason;
        $this->triggeredRuleId = $ruleId;
    }

    public function isBlocked(): bool
    {
        return $this->action === 'BLOCK';
    }

    public function isChallenged(): bool
    {
        return $this->action === 'CHALLENGE';
    }

    public function isAllowed(): bool
    {
        return $this->action === 'ALLOW';
    }

    public function getBlockReason(): ?string
    {
        return $this->blockReason;
    }

    public function getTriggeredRuleId(): ?string
    {
        return $this->triggeredRuleId;
    }

    public function getJa3Hash(): ?string
    {
        return $this->ja3Hash;
    }

    public function setJa3Hash(?string $ja3Hash): void
    {
        $this->ja3Hash = $ja3Hash;
    }

    public function getBotType(): ?string
    {
        return $this->botType;
    }

    public function setBotType(?string $botType): void
    {
        $this->botType = $botType;
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    public function jsonSerialize(): array
    {
        return [
            'ip' => $this->ip,
            'method' => $this->method,
            'uri' => $this->uri,
            'action' => $this->action,
            'block_reason' => $this->blockReason,
            'triggered_rule_id' => $this->triggeredRuleId,
            'anomaly_score' => $this->anomalyScore->jsonSerialize(),
            'bot_type' => $this->botType,
            'ja3_hash' => $this->ja3Hash,
            'timestamp' => $this->timestamp,
        ];
    }
}
