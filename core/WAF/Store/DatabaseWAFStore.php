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
use NetFusion\Core\Database\Connection;
use PDO;

class DatabaseWAFStore implements WAFStoreInterface
{
    protected PDO $pdo;

    public function __construct(PDO|Connection $connection)
    {
        $this->pdo = ($connection instanceof Connection) ? $connection->getPdo() : $connection;
    }

    public function logThreatEvent(ThreatContext $context): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO nf_waf_threat_events (ip, method, uri, action, reason, rule_id, anomaly_score, bot_type, ja3_hash, payload_meta, created_at)
            VALUES (:ip, :method, :uri, :action, :reason, :rule_id, :score, :bot_type, :ja3, :meta, :created_at)
        ");

        $metaJson = json_encode($context->getAnomalyScore()->getMatches(), JSON_THROW_ON_ERROR);

        $stmt->execute([
            ':ip' => $context->getIp(),
            ':method' => $context->getMethod(),
            ':uri' => $context->getUri(),
            ':action' => $context->getAction(),
            ':reason' => $context->getBlockReason(),
            ':rule_id' => $context->getTriggeredRuleId(),
            ':score' => $context->getAnomalyScore()->getScore(),
            ':bot_type' => $context->getBotType(),
            ':ja3' => $context->getJa3Hash(),
            ':meta' => $metaJson,
            ':created_at' => $context->getTimestamp(),
        ]);
    }

    public function getRecentThreatEvents(int $limit = 100): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM nf_waf_threat_events 
            ORDER BY created_at DESC 
            LIMIT :lim
        ");
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function saveIpBlock(string $ip, string $reason, int $expiresAt): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO nf_waf_ip_blocks (ip, reason, expires_at, created_at)
            VALUES (:ip, :reason, :expires_at, :created_at)
            ON CONFLICT(ip) DO UPDATE SET
                reason = :reason_up,
                expires_at = :expires_at_up
        ");

        $now = time();
        $stmt->execute([
            ':ip' => $ip,
            ':reason' => $reason,
            ':expires_at' => $expiresAt,
            ':created_at' => $now,
            ':reason_up' => $reason,
            ':expires_at_up' => $expiresAt,
        ]);
    }

    public function removeIpBlock(string $ip): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM nf_waf_ip_blocks WHERE ip = :ip");
        $stmt->execute([':ip' => $ip]);
        return $stmt->rowCount() > 0;
    }

    public function isIpBlocked(string $ip): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT expires_at FROM nf_waf_ip_blocks 
            WHERE ip = :ip
        ");
        $stmt->execute([':ip' => $ip]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return false;
        }

        $expiresAt = (int)$row['expires_at'];
        if ($expiresAt === 0 || $expiresAt > time()) {
            return true;
        }

        // Clean expired
        $this->removeIpBlock($ip);
        return false;
    }

    public function getActiveIpBlocks(): array
    {
        $now = time();
        $stmt = $this->pdo->prepare("
            SELECT ip, reason, expires_at FROM nf_waf_ip_blocks 
            WHERE expires_at = 0 OR expires_at > :now
        ");
        $stmt->execute([':now' => $now]);

        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[$row['ip']] = [
                'reason' => $row['reason'],
                'expires_at' => (int)$row['expires_at'],
            ];
        }

        return $result;
    }

    public function recordMetric(string $metric, int $count = 1): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO nf_waf_metrics (metric_name, metric_count, updated_at)
            VALUES (:name, :cnt, :updated_at)
            ON CONFLICT(metric_name) DO UPDATE SET
                metric_count = metric_count + :cnt_up,
                updated_at = :updated_at_up
        ");

        $now = time();
        $stmt->execute([
            ':name' => $metric,
            ':cnt' => $count,
            ':updated_at' => $now,
            ':cnt_up' => $count,
            ':updated_at_up' => $now,
        ]);
    }

    public function getMetrics(): array
    {
        $stmt = $this->pdo->query("SELECT metric_name, metric_count FROM nf_waf_metrics");
        $metrics = [];
        if ($stmt) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $metrics[$row['metric_name']] = (int)$row['metric_count'];
            }
        }
        return $metrics;
    }
}
