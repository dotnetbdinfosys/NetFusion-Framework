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

namespace NetFusion\Core\Gateway\Storage;

use NetFusion\Core\Database\Connection;
use NetFusion\Core\Gateway\Contracts\GatewayStoreInterface;
use NetFusion\Core\Gateway\Models\Route;
use NetFusion\Core\Gateway\Models\Upstream;
use NetFusion\Core\Gateway\Models\UpstreamPool;

/**
 * SQLite/MySQL-backed implementation of GatewayStoreInterface.
 * Falls back to in-memory storage when no PDO connection is available.
 */
class DatabaseGatewayStore implements GatewayStoreInterface
{
    private ?\PDO $pdo = null;

    /** In-memory fallback stores */
    private array $memRoutes         = [];
    private array $memUpstreamHealth = [];
    private array $memRateBuckets    = [];

    public function __construct(?Connection $db = null)
    {
        if ($db !== null) {
            try {
                $this->pdo = $db->getPdo();
                $this->ensureSchema();
            } catch (\Throwable) {
                $this->pdo = null;
            }
        }
    }

    // -------------------------------------------------------------------------
    // Routes
    // -------------------------------------------------------------------------

    public function saveRoute(Route $route): void
    {
        if ($this->pdo !== null) {
            $stmt = $this->pdo->prepare(
                'INSERT OR REPLACE INTO nf_gateway_routes (id, path_prefix, hostname, config, created_at)
                 VALUES (:id, :prefix, :host, :config, :ts)'
            );
            $stmt->execute([
                ':id'     => $route->getId(),
                ':prefix' => $route->getPathPrefix(),
                ':host'   => $route->getHostname(),
                ':config' => json_encode($route->toArray()),
                ':ts'     => date('Y-m-d H:i:s'),
            ]);
        } else {
            $this->memRoutes[$route->getId()] = $route->toArray();
        }
    }

    public function loadRoutes(): array
    {
        // Route reconstruction from JSON is complex; return raw array for now
        // In production, a RouteFactory would reconstruct Route objects
        if ($this->pdo !== null) {
            $rows = $this->pdo->query('SELECT config FROM nf_gateway_routes ORDER BY created_at ASC')
                              ->fetchAll(\PDO::FETCH_COLUMN);
            return array_map(fn($row) => json_decode($row, true), $rows);
        }
        return array_values($this->memRoutes);
    }

    public function deleteRoute(string $routeId): void
    {
        if ($this->pdo !== null) {
            $this->pdo->prepare('DELETE FROM nf_gateway_routes WHERE id = ?')->execute([$routeId]);
        } else {
            unset($this->memRoutes[$routeId]);
        }
    }

    // -------------------------------------------------------------------------
    // Upstream Health
    // -------------------------------------------------------------------------

    public function saveUpstreamHealth(string $upstreamId, bool $healthy, int $failures): void
    {
        if ($this->pdo !== null) {
            $stmt = $this->pdo->prepare(
                'INSERT OR REPLACE INTO nf_gateway_upstream_health (upstream_id, healthy, failures, checked_at)
                 VALUES (:id, :h, :f, :ts)'
            );
            $stmt->execute([
                ':id' => $upstreamId,
                ':h'  => (int)$healthy,
                ':f'  => $failures,
                ':ts' => date('Y-m-d H:i:s'),
            ]);
        } else {
            $this->memUpstreamHealth[$upstreamId] = ['healthy' => $healthy, 'failures' => $failures];
        }
    }

    public function loadUpstreamHealth(string $upstreamId): ?array
    {
        if ($this->pdo !== null) {
            $stmt = $this->pdo->prepare(
                'SELECT healthy, failures FROM nf_gateway_upstream_health WHERE upstream_id = ?'
            );
            $stmt->execute([$upstreamId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row) {
                return ['healthy' => (bool)$row['healthy'], 'failures' => (int)$row['failures']];
            }
            return null;
        }
        return $this->memUpstreamHealth[$upstreamId] ?? null;
    }

    // -------------------------------------------------------------------------
    // Rate Limiting
    // -------------------------------------------------------------------------

    public function setRateBucket(string $key, int $tokens, int $resetAt): void
    {
        if ($this->pdo !== null) {
            $stmt = $this->pdo->prepare(
                'INSERT OR REPLACE INTO nf_gateway_rate_buckets (bucket_key, tokens, reset_at)
                 VALUES (:k, :t, :r)'
            );
            $stmt->execute([':k' => $key, ':t' => $tokens, ':r' => $resetAt]);
        } else {
            $this->memRateBuckets[$key] = ['tokens' => $tokens, 'reset_at' => $resetAt];
        }
    }

    public function getRateBucket(string $key): ?array
    {
        if ($this->pdo !== null) {
            $stmt = $this->pdo->prepare(
                'SELECT tokens, reset_at FROM nf_gateway_rate_buckets WHERE bucket_key = ?'
            );
            $stmt->execute([$key]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row ? ['tokens' => (int)$row['tokens'], 'reset_at' => (int)$row['reset_at']] : null;
        }
        return $this->memRateBuckets[$key] ?? null;
    }

    // -------------------------------------------------------------------------
    // Request Logging
    // -------------------------------------------------------------------------

    public function logRequest(array $entry): void
    {
        if ($this->pdo !== null) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO nf_gateway_request_log
                 (route_id, upstream_id, method, path, status_code, latency_ms, client_ip, logged_at)
                 VALUES (:route, :upstream, :method, :path, :status, :latency, :ip, :ts)'
            );
            $stmt->execute([
                ':route'    => $entry['route_id']    ?? '',
                ':upstream' => $entry['upstream_id'] ?? '',
                ':method'   => $entry['method']      ?? '',
                ':path'     => $entry['path']        ?? '',
                ':status'   => $entry['status_code'] ?? 0,
                ':latency'  => $entry['latency_ms']  ?? 0.0,
                ':ip'       => $entry['client_ip']   ?? '',
                ':ts'       => date('Y-m-d H:i:s'),
            ]);
        }
        // In-memory: logs are silently discarded (CLI/test mode)
    }

    // -------------------------------------------------------------------------
    // Schema bootstrap
    // -------------------------------------------------------------------------

    private function ensureSchema(): void
    {
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS nf_gateway_routes (
                id         TEXT PRIMARY KEY,
                path_prefix TEXT NOT NULL,
                hostname   TEXT NOT NULL DEFAULT \'*\',
                config     TEXT NOT NULL,
                created_at TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS nf_gateway_upstream_health (
                upstream_id TEXT PRIMARY KEY,
                healthy     INTEGER NOT NULL DEFAULT 1,
                failures    INTEGER NOT NULL DEFAULT 0,
                checked_at  TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS nf_gateway_rate_buckets (
                bucket_key TEXT PRIMARY KEY,
                tokens     INTEGER NOT NULL,
                reset_at   INTEGER NOT NULL
            );
            CREATE TABLE IF NOT EXISTS nf_gateway_request_log (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                route_id    TEXT NOT NULL,
                upstream_id TEXT NOT NULL,
                method      TEXT NOT NULL,
                path        TEXT NOT NULL,
                status_code INTEGER NOT NULL,
                latency_ms  REAL NOT NULL,
                client_ip   TEXT NOT NULL,
                logged_at   TEXT NOT NULL
            );
        ');
    }

    public function hasPdoConnection(): bool
    {
        return $this->pdo !== null;
    }
}
