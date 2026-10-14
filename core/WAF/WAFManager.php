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

namespace NetFusion\Core\WAF;

use NetFusion\Core\WAF\Contracts\WAFInterface;
use NetFusion\Core\WAF\Contracts\ThreatDetectorInterface;
use NetFusion\Core\WAF\Contracts\WAFStoreInterface;
use NetFusion\Core\WAF\IP\IPReputation;
use NetFusion\Core\WAF\IP\ThreatContext;
use NetFusion\Core\WAF\Detectors\AnomalyScore;
use NetFusion\Core\WAF\Detectors\SQLiDetector;
use NetFusion\Core\WAF\Detectors\XSSDetector;
use NetFusion\Core\WAF\Detectors\RCEDetector;
use NetFusion\Core\WAF\Detectors\PathTraversalDetector;
use NetFusion\Core\WAF\Detectors\ProtocolViolationDetector;
use NetFusion\Core\WAF\Bot\BotDetector;
use NetFusion\Core\WAF\Bot\JA3Fingerprinter;
use NetFusion\Core\WAF\Bot\ChallengeEngine;
use NetFusion\Core\WAF\DDoS\AdaptiveRateLimiter;
use NetFusion\Core\WAF\Rules\VirtualPatchEngine;
use NetFusion\Core\WAF\Rules\WAFRule;
use NetFusion\Core\WAF\Store\ArrayWAFStore;

class WAFManager implements WAFInterface
{
    protected IPReputation $ipReputation;
    protected AdaptiveRateLimiter $rateLimiter;
    protected BotDetector $botDetector;
    protected ChallengeEngine $challengeEngine;
    protected VirtualPatchEngine $patchEngine;
    protected WAFStoreInterface $store;

    /**
     * @var array<ThreatDetectorInterface>
     */
    protected array $detectors = [];

    protected bool $enabled = true;
    protected float $warningThreshold = 3.0;
    protected float $blockingThreshold = 5.0;
    protected bool $autoBanEnabled = true;
    protected int $autoBanTtl = 3600; // 1 hour

    /**
     * @param array<string, mixed> $config
     * @param WAFStoreInterface|null $store
     */
    public function __construct(
        protected array $config = [],
        ?WAFStoreInterface $store = null
    ) {
        $this->store = $store ?? new ArrayWAFStore();
        $this->ipReputation = new IPReputation();
        $this->rateLimiter = new AdaptiveRateLimiter(
            maxRequests: (int)($config['rate_limit']['max_requests'] ?? 100),
            windowSeconds: (int)($config['rate_limit']['window_seconds'] ?? 60),
            banThresholdRatio: (int)($config['rate_limit']['ban_threshold_ratio'] ?? 3),
            banDurationSeconds: (int)($config['rate_limit']['ban_duration'] ?? 900)
        );

        $ja3 = new JA3Fingerprinter();
        $this->botDetector = new BotDetector($ja3);
        $this->challengeEngine = new ChallengeEngine((string)($config['secret'] ?? 'netfusion-waf-secret-32b'));
        $this->patchEngine = new VirtualPatchEngine();

        $this->warningThreshold = (float)($config['thresholds']['warning'] ?? 3.0);
        $this->blockingThreshold = (float)($config['thresholds']['blocking'] ?? 5.0);
        $this->autoBanEnabled = (bool)($config['auto_ban']['enabled'] ?? true);
        $this->autoBanTtl = (int)($config['auto_ban']['ttl'] ?? 3600);

        $this->registerDefaultDetectors();
        $this->bootstrapConfigRules();
    }

    protected function registerDefaultDetectors(): void
    {
        $this->detectors = [
            new SQLiDetector(),
            new XSSDetector(),
            new RCEDetector(),
            new PathTraversalDetector(),
            new ProtocolViolationDetector(),
        ];
    }

    protected function bootstrapConfigRules(): void
    {
        // Whitelist from config
        $whitelist = (array)($this->config['ip_whitelist'] ?? []);
        foreach ($whitelist as $ip) {
            $this->ipReputation->addWhitelist((string)$ip, 'Config Whitelist');
        }

        // Blacklist from config
        $blacklist = (array)($this->config['ip_blacklist'] ?? []);
        foreach ($blacklist as $ip) {
            $this->ipReputation->addBlacklist((string)$ip, 'Config Blacklist');
        }
    }

    public function getIpReputation(): IPReputation
    {
        return $this->ipReputation;
    }

    public function getRateLimiter(): AdaptiveRateLimiter
    {
        return $this->rateLimiter;
    }

    public function getBotDetector(): BotDetector
    {
        return $this->botDetector;
    }

    public function getChallengeEngine(): ChallengeEngine
    {
        return $this->challengeEngine;
    }

    public function getPatchEngine(): VirtualPatchEngine
    {
        return $this->patchEngine;
    }

    public function getStore(): WAFStoreInterface
    {
        return $this->store;
    }

    public function addDetector(ThreatDetectorInterface $detector): void
    {
        $this->detectors[] = $detector;
    }

    public function patch(
        string $id,
        string $field,
        string $operator,
        mixed $pattern,
        string $description = '',
        string $action = 'BLOCK',
        float $score = 5.0
    ): WAFRule {
        return $this->patchEngine->patch($id, $field, $operator, $pattern, $description, $action, $score);
    }

    public function isBlocked(string $ip): bool
    {
        return $this->ipReputation->isBlacklisted($ip) || $this->store->isIpBlocked($ip) || $this->rateLimiter->isBanned($ip);
    }

    public function blockIp(string $ip, string $reason = 'Manual block', int $ttl = 86400): void
    {
        $this->ipReputation->addBlacklist($ip, $reason, $ttl);
        $this->store->saveIpBlock($ip, $reason, $ttl > 0 ? time() + $ttl : 0);
    }

    public function unblockIp(string $ip): bool
    {
        $r1 = $this->ipReputation->remove($ip);
        $r2 = $this->store->removeIpBlock($ip);
        $this->rateLimiter->reset($ip);
        return $r1 || $r2;
    }

    /**
     * Inspect incoming HTTP request against the complete WAF security stack.
     */
    public function inspect(
        string $ip,
        string $method,
        string $uri,
        array $headers = [],
        array $query = [],
        array|string $body = [],
        array $cookies = []
    ): ThreatContext {
        $this->store->recordMetric('inspected');

        $anomalyScore = new AnomalyScore(
            warningThreshold: $this->warningThreshold,
            blockingThreshold: $this->blockingThreshold
        );

        $context = new ThreatContext(
            ip: $ip,
            method: $method,
            uri: $uri,
            headers: $headers,
            query: $query,
            body: $body,
            cookies: $cookies,
            anomalyScore: $anomalyScore
        );

        // 1. IP Whitelist Check (Fast Path)
        if ($this->ipReputation->isWhitelisted($ip)) {
            $context->setAction('ALLOW');
            $this->store->recordMetric('passed');
            return $context;
        }

        // 2. IP Blacklist & Active Block Check
        if ($this->isBlocked($ip)) {
            $context->setAction('BLOCK', 'Client IP is blacklisted or banned', 'IP_REPUTATION_BLOCK');
            $this->store->recordMetric('blocked');
            $this->store->logThreatEvent($context);
            return $context;
        }

        // 3. Layer 7 DDoS Adaptive Rate Limiting
        $rateCheck = $this->rateLimiter->hit($ip);
        if (!$rateCheck['allowed']) {
            if ($rateCheck['is_banned']) {
                $context->setAction('BLOCK', 'Layer 7 DDoS burst threshold exceeded (Auto-Banned)', 'DDOS_BURST_BLOCK');
                if ($this->autoBanEnabled) {
                    $this->blockIp($ip, 'DDoS burst auto-ban', $this->autoBanTtl);
                }
            } else {
                $context->setAction('BLOCK', 'Rate limit exceeded', 'RATE_LIMIT_EXCEEDED');
            }
            $this->store->recordMetric('blocked');
            $this->store->logThreatEvent($context);
            return $context;
        }

        // 4. Bot & Client Fingerprint Detection
        $botResult = $this->botDetector->detect($context);
        if ($botResult['is_bot']) {
            $context->setBotType($botResult['bot_type']);
            if ($botResult['is_bad_bot']) {
                $context->setAction('BLOCK', $botResult['description'], 'BAD_BOT_DETECTED');
                $anomalyScore->addMatch(
                    ruleId: 'BAD_BOT',
                    detector: 'BotDetector',
                    field: 'headers.user-agent',
                    points: $botResult['score'],
                    matchSnippet: (string)($headers['user-agent'] ?? ''),
                    message: (string)$botResult['description']
                );
                $this->store->recordMetric('blocked');
                $this->store->logThreatEvent($context);
                return $context;
            } else {
                // Challenge suspicious bots
                $context->setAction('CHALLENGE', $botResult['description'], 'BOT_CHALLENGE_REQUIRED');
            }
        }

        // 5. Virtual Patch Engine Evaluation
        $this->patchEngine->evaluate($context);
        if ($context->isBlocked()) {
            $this->store->recordMetric('blocked');
            $this->store->logThreatEvent($context);
            return $context;
        }

        // 6. OWASP CRS Threat Detectors (URI, Query, Headers, Body, Cookies)
        $fieldsToInspect = [
            'uri' => $uri,
            'query' => $query,
            'headers' => $headers,
            'body' => $body,
            'cookies' => $cookies,
        ];

        foreach ($this->detectors as $detector) {
            foreach ($fieldsToInspect as $fieldName => $val) {
                $detector->inspect($fieldName, $val, $context, $anomalyScore);
            }
        }

        // 7. Decision based on Anomaly Score
        if ($anomalyScore->isBlocked()) {
            $matches = $anomalyScore->getMatches();
            $leadMatch = $matches[0] ?? null;
            $reason = $leadMatch ? "Threat detected: {$leadMatch['message']}" : 'Anomaly score exceeded blocking threshold';
            $ruleId = $leadMatch ? $leadMatch['rule_id'] : 'ANOMALY_THRESHOLD_BLOCK';

            $context->setAction('BLOCK', $reason, $ruleId);

            // Auto-ban IP on high severity attacks (e.g. score >= 10)
            if ($this->autoBanEnabled && $anomalyScore->getScore() >= 10.0) {
                $this->blockIp($ip, "High anomaly score ({$anomalyScore->getScore()}) auto-ban: {$ruleId}", $this->autoBanTtl);
            }

            $this->store->recordMetric('blocked');
            $this->store->logThreatEvent($context);
            return $context;
        }

        if ($anomalyScore->isWarning() && !$context->isChallenged()) {
            $context->setAction('CHALLENGE', 'Anomaly warning threshold reached', 'ANOMALY_WARNING_CHALLENGE');
            $this->store->recordMetric('challenged');
            $this->store->logThreatEvent($context);
            return $context;
        }

        if ($context->isChallenged()) {
            $this->store->recordMetric('challenged');
            $this->store->logThreatEvent($context);
            return $context;
        }

        $context->setAction('ALLOW');
        $this->store->recordMetric('passed');
        return $context;
    }
}
