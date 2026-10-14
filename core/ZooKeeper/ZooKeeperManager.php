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

namespace NetFusion\Core\ZooKeeper;

use NetFusion\Core\ZooKeeper\Enums\CreateMode;
use NetFusion\Core\ZooKeeper\Models\DataTree;
use NetFusion\Core\ZooKeeper\Models\Stat;
use NetFusion\Core\ZooKeeper\Models\ZNode;
use NetFusion\Core\ZooKeeper\Protocol\ZabProtocol;
use NetFusion\Core\ZooKeeper\Session\Session;
use NetFusion\Core\ZooKeeper\Session\SessionManager;
use NetFusion\Core\ZooKeeper\Watch\WatchManager;

/**
 * Master Enterprise ZooKeeper Facade & Orchestrator (Apache ZooKeeper Architecture).
 * Coordinates hierarchical ZNode namespace, client session lifecycle with ephemeral auto-eviction,
 * reactive one-time watchers, and ZAB atomic broadcast consensus.
 */
class ZooKeeperManager
{
    /** @var array<string, mixed> */
    protected array $config;
    protected DataTree $dataTree;
    protected WatchManager $watchManager;
    protected SessionManager $sessionManager;
    protected ZabProtocol $zab;
    protected ?Session $defaultSession = null;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->watchManager = new WatchManager();
        $this->dataTree = new DataTree($this->watchManager);
        $this->sessionManager = new SessionManager();

        $serverId = (int)($config['server_id'] ?? 1);
        $clusterSize = (int)($config['cluster_size'] ?? 3);
        $this->zab = new ZabProtocol($serverId, $clusterSize);

        // Auto-elect this server if standalone
        if ($clusterSize === 1) {
            $this->zab->castVote($serverId, $this->zab->createVote());
        }
    }

    public function getDataTree(): DataTree
    {
        return $this->dataTree;
    }

    public function getWatchManager(): WatchManager
    {
        return $this->watchManager;
    }

    public function getSessionManager(): SessionManager
    {
        return $this->sessionManager;
    }

    public function getZab(): ZabProtocol
    {
        return $this->zab;
    }

    /**
     * Get or establish active client session.
     */
    public function session(int $timeoutMs = 30000): Session
    {
        if ($this->defaultSession === null || $this->defaultSession->isClosed()) {
            $this->defaultSession = $this->sessionManager->createSession($timeoutMs);
        }
        return $this->defaultSession;
    }

    /**
     * Create a ZNode.
     */
    public function create(
        string $path,
        ?string $data = null,
        CreateMode $mode = CreateMode::PERSISTENT,
        ?int $sessionId = null
    ): string {
        $sessId = $sessionId ?? $this->session()->getId();
        $zxid = $this->zab->propose('create', $path, $data);

        $createdPath = $this->dataTree->create($path, $data, $mode, $zxid, $sessId);

        if ($mode->isEphemeral()) {
            $sess = $this->sessionManager->getSession($sessId);
            $sess?->registerEphemeralNode($createdPath);
        }

        return $createdPath;
    }

    /**
     * Check if a ZNode exists.
     */
    public function exists(string $path, ?callable $watchCallback = null, ?int $sessionId = null): ?Stat
    {
        $sessId = $sessionId ?? $this->session()->getId();
        return $this->dataTree->exists($path, $watchCallback, $sessId);
    }

    /**
     * Get data of a ZNode.
     */
    public function getData(string $path, ?callable $watchCallback = null, ?Stat &$stat = null, ?int $sessionId = null): ?string
    {
        $sessId = $sessionId ?? $this->session()->getId();
        return $this->dataTree->getData($path, $watchCallback, $stat, $sessId);
    }

    /**
     * Set data for a ZNode.
     */
    public function setData(string $path, ?string $data, int $version = -1): Stat
    {
        $zxid = $this->zab->propose('setData', $path, $data);
        return $this->dataTree->setData($path, $data, $version, $zxid);
    }

    /**
     * Get children names of a ZNode.
     *
     * @return array<string>
     */
    public function getChildren(string $path, ?callable $watchCallback = null, ?int $sessionId = null): array
    {
        $sessId = $sessionId ?? $this->session()->getId();
        return $this->dataTree->getChildren($path, $watchCallback, $sessId);
    }

    /**
     * Delete a ZNode.
     */
    public function delete(string $path, int $version = -1): bool
    {
        $zxid = $this->zab->propose('delete', $path);
        return $this->dataTree->delete($path, $version, $zxid);
    }

    /**
     * Send heartbeat keep-alive for a session.
     */
    public function heartbeat(?int $sessionId = null): bool
    {
        $sessId = $sessionId ?? $this->session()->getId();
        return $this->sessionManager->heartbeat($sessId);
    }

    /**
     * Expire or close a session and cascade-delete all its ephemeral ZNodes.
     *
     * @return array<string> Deleted ephemeral paths
     */
    public function closeSession(?int $sessionId = null): array
    {
        $sessId = $sessionId ?? $this->session()->getId();
        $session = $this->sessionManager->closeSession($sessId);
        if ($session === null) {
            return [];
        }

        $deletedPaths = [];
        foreach ($session->getEphemeralNodes() as $path) {
            try {
                if ($this->dataTree->getNode($path) !== null) {
                    $this->dataTree->delete($path);
                    $deletedPaths[] = $path;
                }
            } catch (\Throwable) {
                // ignore if already deleted
            }
        }

        if ($this->defaultSession?->getId() === $sessId) {
            $this->defaultSession = null;
        }

        return $deletedPaths;
    }

    /**
     * Sweep all expired sessions and clean up their ephemeral nodes.
     *
     * @return array<string>
     */
    public function sweepExpiredSessions(float $customTime = 0.0): array
    {
        $expiredSessions = $this->sessionManager->sweepExpired($customTime);
        $deletedPaths = [];

        foreach ($expiredSessions as $session) {
            foreach ($session->getEphemeralNodes() as $path) {
                try {
                    if ($this->dataTree->getNode($path) !== null) {
                        $this->dataTree->delete($path);
                        $deletedPaths[] = $path;
                    }
                } catch (\Throwable) {
                    // ignore
                }
            }
        }

        return $deletedPaths;
    }
}
