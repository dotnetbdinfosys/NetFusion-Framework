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
use NetFusion\Core\Database\Connection;
use PDO;

class DatabaseCEPStore implements CEPStoreInterface
{
    protected PDO $pdo;
    protected string $alertsTable = 'nf_cep_alerts';
    protected string $stateTable = 'nf_cep_state_snapshots';
    protected string $metricsTable = 'nf_cep_metrics';

    public function __construct(PDO|Connection $connection, array $options = [])
    {
        $this->pdo = $connection instanceof Connection ? $connection->getPdo() : $connection;
        $this->alertsTable = $options['alerts_table'] ?? 'nf_cep_alerts';
        $this->stateTable = $options['state_table'] ?? 'nf_cep_state_snapshots';
        $this->metricsTable = $options['metrics_table'] ?? 'nf_cep_metrics';
    }

    public function saveAlert(array $alertData): string
    {
        $id = $alertData['id'] ?? self::generateUuid();
        $ruleName = $alertData['rule_name'] ?? ($alertData['rule'] ?? 'unknown');
        $stream = $alertData['stream'] ?? 'default';
        $partitionKey = $alertData['partition_key'] ?? ($alertData['key'] ?? 'global');
        $severity = $alertData['severity'] ?? 'INFO';
        $payload = is_array($alertData['payload'] ?? null)
            ? json_encode($alertData['payload'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : json_encode($alertData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $createdAt = isset($alertData['created_at'])
            ? (is_numeric($alertData['created_at']) ? date('Y-m-d H:i:s', (int) $alertData['created_at']) : (string) $alertData['created_at'])
            : date('Y-m-d H:i:s');

        $sql = "INSERT INTO {$this->alertsTable} (id, rule_name, stream, partition_key, severity, payload, created_at)
                VALUES (:id, :rule_name, :stream, :partition_key, :severity, :payload, :created_at)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':rule_name' => $ruleName,
            ':stream' => $stream,
            ':partition_key' => $partitionKey,
            ':severity' => $severity,
            ':payload' => $payload,
            ':created_at' => $createdAt,
        ]);

        return $id;
    }

    public function getAlerts(array $filter = [], int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT * FROM {$this->alertsTable}";
        $params = [];
        $where = [];

        if (isset($filter['rule_name'])) {
            $where[] = "rule_name = :rule_name";
            $params[':rule_name'] = $filter['rule_name'];
        }
        if (isset($filter['stream'])) {
            $where[] = "stream = :stream";
            $params[':stream'] = $filter['stream'];
        }
        if (isset($filter['partition_key'])) {
            $where[] = "partition_key = :partition_key";
            $params[':partition_key'] = $filter['partition_key'];
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            if (isset($row['payload']) && is_string($row['payload'])) {
                $decoded = json_decode($row['payload'], true);
                if (is_array($decoded)) {
                    $row['payload'] = $decoded;
                }
            }
        }

        return $rows;
    }

    public function saveState(string $ruleName, string $partitionKey, array $stateData): void
    {
        $jsonData = json_encode($stateData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $now = date('Y-m-d H:i:s');

        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $sql = "INSERT INTO {$this->stateTable} (rule_name, partition_key, state_data, updated_at)
                    VALUES (:rule_name, :partition_key, :state_data, :updated_at)
                    ON CONFLICT(rule_name, partition_key) DO UPDATE SET
                    state_data = :state_data_up, updated_at = :updated_at_up";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':rule_name' => $ruleName,
                ':partition_key' => $partitionKey,
                ':state_data' => $jsonData,
                ':updated_at' => $now,
                ':state_data_up' => $jsonData,
                ':updated_at_up' => $now,
            ]);
        } elseif ($driver === 'mysql') {
            $sql = "INSERT INTO {$this->stateTable} (rule_name, partition_key, state_data, updated_at)
                    VALUES (:rule_name, :partition_key, :state_data, :updated_at)
                    ON DUPLICATE KEY UPDATE state_data = VALUES(state_data), updated_at = VALUES(updated_at)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':rule_name' => $ruleName,
                ':partition_key' => $partitionKey,
                ':state_data' => $jsonData,
                ':updated_at' => $now,
            ]);
        } else {
            // Standard fallback
            $del = $this->pdo->prepare("DELETE FROM {$this->stateTable} WHERE rule_name = :rule AND partition_key = :key");
            $del->execute([':rule' => $ruleName, ':key' => $partitionKey]);

            $ins = $this->pdo->prepare("INSERT INTO {$this->stateTable} (rule_name, partition_key, state_data, updated_at) VALUES (:rule, :key, :data, :up)");
            $ins->execute([':rule' => $ruleName, ':key' => $partitionKey, ':data' => $jsonData, ':up' => $now]);
        }
    }

    public function loadState(string $ruleName, string $partitionKey): ?array
    {
        $stmt = $this->pdo->prepare("SELECT state_data FROM {$this->stateTable} WHERE rule_name = :rule AND partition_key = :key LIMIT 1");
        $stmt->execute([':rule' => $ruleName, ':key' => $partitionKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || !isset($row['state_data'])) {
            return null;
        }

        $decoded = json_decode((string) $row['state_data'], true);
        return is_array($decoded) ? $decoded : null;
    }

    public function clearState(?string $ruleName = null, ?string $partitionKey = null): void
    {
        if ($ruleName === null && $partitionKey === null) {
            $this->pdo->exec("DELETE FROM {$this->stateTable}");
            return;
        }

        $where = [];
        $params = [];
        if ($ruleName !== null) {
            $where[] = "rule_name = :rule";
            $params[':rule'] = $ruleName;
        }
        if ($partitionKey !== null) {
            $where[] = "partition_key = :key";
            $params[':key'] = $partitionKey;
        }

        $sql = "DELETE FROM {$this->stateTable} WHERE " . implode(' AND ', $where);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function recordMetric(string $metricName, float $value, array $tags = []): void
    {
        $sql = "INSERT INTO {$this->metricsTable} (metric_name, metric_value, tags, recorded_at)
                VALUES (:metric_name, :metric_value, :tags, :recorded_at)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':metric_name' => $metricName,
            ':metric_value' => $value,
            ':tags' => json_encode($tags, JSON_UNESCAPED_SLASHES),
            ':recorded_at' => date('Y-m-d H:i:s'),
        ]);
    }

    protected static function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
