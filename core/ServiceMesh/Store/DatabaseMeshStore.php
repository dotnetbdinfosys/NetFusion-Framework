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

namespace NetFusion\Core\ServiceMesh\Store;

use NetFusion\Core\Database\Connection;
use NetFusion\Core\ServiceMesh\Contracts\MeshStoreInterface;
use NetFusion\Core\ServiceMesh\Health\ServiceInstance;
use NetFusion\Core\ServiceMesh\Routing\TrafficRoute;
use NetFusion\Core\ServiceMesh\Security\MeshAuthorizationPolicy;
use NetFusion\Core\ServiceMesh\Security\SpiffeId;
use PDO;

class DatabaseMeshStore implements MeshStoreInterface
{
    protected PDO $pdo;
    protected string $servicesTable = 'nf_mesh_services';
    protected string $routesTable = 'nf_mesh_routes';
    protected string $policiesTable = 'nf_mesh_policies';
    protected string $certsTable = 'nf_mesh_certificates';
    protected string $metricsTable = 'nf_mesh_metrics';

    public function __construct(PDO|Connection $connection, array $options = [])
    {
        $this->pdo = $connection instanceof Connection ? $connection->getPdo() : $connection;
        $this->servicesTable = $options['services_table'] ?? 'nf_mesh_services';
        $this->routesTable = $options['routes_table'] ?? 'nf_mesh_routes';
        $this->policiesTable = $options['policies_table'] ?? 'nf_mesh_policies';
        $this->certsTable = $options['certs_table'] ?? 'nf_mesh_certificates';
        $this->metricsTable = $options['metrics_table'] ?? 'nf_mesh_metrics';
    }

    public function saveServiceInstance(ServiceInstance $instance): void
    {
        $sql = "INSERT INTO {$this->servicesTable} (instance_id, service_name, host, port, subset, weight, status, spiffe_id, metadata_json, updated_at)
                VALUES (:id, :service, :host, :port, :subset, :weight, :status, :spiffe_id, :meta, :updated_at)";

        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $sql .= " ON CONFLICT(instance_id) DO UPDATE SET
                      host = excluded.host, port = excluded.port, subset = excluded.subset,
                      weight = excluded.weight, status = excluded.status, updated_at = excluded.updated_at";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id' => $instance->getId(),
            ':service' => $instance->getServiceName(),
            ':host' => $instance->getHost(),
            ':port' => $instance->getPort(),
            ':subset' => $instance->getSubset(),
            ':weight' => $instance->getWeight(),
            ':status' => $instance->getStatus(),
            ':spiffe_id' => $instance->getSpiffeId()?->toString(),
            ':meta' => json_encode($instance->getMetadata(), JSON_UNESCAPED_SLASHES),
            ':updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function removeServiceInstance(string $serviceName, string $instanceId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->servicesTable} WHERE service_name = :s AND instance_id = :id");
        $stmt->execute([':s' => $serviceName, ':id' => $instanceId]);
        return $stmt->rowCount() > 0;
    }

    public function getServiceInstances(string $serviceName): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->servicesTable} WHERE service_name = :s");
        $stmt->execute([':s' => $serviceName]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $instances = [];
        foreach ($rows as $row) {
            $instances[] = new ServiceInstance(
                (string) $row['instance_id'],
                (string) $row['service_name'],
                (string) $row['host'],
                (int) $row['port'],
                (string) ($row['subset'] ?? 'v1'),
                (int) ($row['weight'] ?? 100),
                (string) ($row['status'] ?? 'HEALTHY'),
                isset($row['spiffe_id']) ? SpiffeId::fromString((string) $row['spiffe_id']) : null,
                json_decode((string) ($row['metadata_json'] ?? '{}'), true) ?: []
            );
        }

        return $instances;
    }

    public function getAllServices(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM {$this->servicesTable} ORDER BY service_name ASC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($rows as $row) {
            $sName = (string) $row['service_name'];
            if (!isset($grouped[$sName])) {
                $grouped[$sName] = [];
            }
            $grouped[$sName][] = new ServiceInstance(
                (string) $row['instance_id'],
                $sName,
                (string) $row['host'],
                (int) $row['port'],
                (string) ($row['subset'] ?? 'v1'),
                (int) ($row['weight'] ?? 100),
                (string) ($row['status'] ?? 'HEALTHY'),
                isset($row['spiffe_id']) ? SpiffeId::fromString((string) $row['spiffe_id']) : null,
                json_decode((string) ($row['metadata_json'] ?? '{}'), true) ?: []
            );
        }

        return $grouped;
    }

    public function saveRoute(TrafficRoute $route): void
    {
        $sql = "INSERT INTO {$this->routesTable} (name, service_name, config_json, updated_at)
                VALUES (:name, :service, :config, :updated_at)";

        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $sql .= " ON CONFLICT(name) DO UPDATE SET service_name = excluded.service_name, config_json = excluded.config_json, updated_at = excluded.updated_at";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':name' => $route->getName(),
            ':service' => $route->getServiceName(),
            ':config' => json_encode($route->toArray(), JSON_UNESCAPED_SLASHES),
            ':updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function getRoutes(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM {$this->routesTable}");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function savePolicy(MeshAuthorizationPolicy $policy): void
    {
        $sql = "INSERT INTO {$this->policiesTable} (name, action, config_json, updated_at)
                VALUES (:name, :action, :config, :updated_at)";

        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $sql .= " ON CONFLICT(name) DO UPDATE SET action = excluded.action, config_json = excluded.config_json, updated_at = excluded.updated_at";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':name' => $policy->getName(),
            ':action' => $policy->getAction(),
            ':config' => json_encode($policy->toArray(), JSON_UNESCAPED_SLASHES),
            ':updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function getPolicies(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM {$this->policiesTable}");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveCertificate(string $spiffeId, array $certData): void
    {
        $sql = "INSERT INTO {$this->certsTable} (spiffe_id, cert_pem, key_pem, ca_pem, expires_at, updated_at)
                VALUES (:spiffe_id, :cert, :key, :ca, :expires, :updated_at)";

        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $sql .= " ON CONFLICT(spiffe_id) DO UPDATE SET cert_pem = excluded.cert_pem, key_pem = excluded.key_pem, expires_at = excluded.expires_at, updated_at = excluded.updated_at";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':spiffe_id' => $spiffeId,
            ':cert' => $certData['cert'],
            ':key' => $certData['key'],
            ':ca' => $certData['ca'],
            ':expires' => date('Y-m-d H:i:s', $certData['expires_at']),
            ':updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function getCertificate(string $spiffeId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->certsTable} WHERE spiffe_id = :id LIMIT 1");
        $stmt->execute([':id' => $spiffeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return [
            'cert' => (string) $row['cert_pem'],
            'key' => (string) $row['key_pem'],
            'ca' => (string) $row['ca_pem'],
            'expires_at' => strtotime((string) $row['expires_at']),
            'spiffe_id' => (string) $row['spiffe_id'],
        ];
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
}
