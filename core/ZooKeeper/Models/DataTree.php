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

namespace NetFusion\Core\ZooKeeper\Models;

use NetFusion\Core\ZooKeeper\Enums\CreateMode;
use NetFusion\Core\ZooKeeper\Enums\WatcherEvent;
use NetFusion\Core\ZooKeeper\Exceptions\EphemeralOnEphemeralException;
use NetFusion\Core\ZooKeeper\Exceptions\NodeExistsException;
use NetFusion\Core\ZooKeeper\Exceptions\NodeNotFoundException;
use NetFusion\Core\ZooKeeper\Exceptions\NotEmptyException;
use NetFusion\Core\ZooKeeper\Watch\WatchManager;

/**
 * The Hierarchical ZooKeeper In-Memory Data Tree.
 * Manages atomic path lookups, ZNode mutations, ephemeral node tracking, and reactive watch triggers.
 */
class DataTree
{
    protected ZNode $root;
    protected WatchManager $watchManager;
    /** @var array<string, ZNode> [normalizedPath => ZNode] Flat index for fast O(1) access */
    protected array $nodesByPath = [];

    public function __construct(?WatchManager $watchManager = null)
    {
        $this->watchManager = $watchManager ?? new WatchManager();
        $this->root = new ZNode('/', null, CreateMode::PERSISTENT, new Stat());
        $this->nodesByPath['/'] = $this->root;
    }

    public function getWatchManager(): WatchManager
    {
        return $this->watchManager;
    }

    public function getRoot(): ZNode
    {
        return $this->root;
    }

    /**
     * Normalize path string.
     */
    public function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');
        return preg_replace('#/+#', '/', $path) ?: '/';
    }

    /**
     * Create a new ZNode in the hierarchy.
     *
     * @throws NodeExistsException
     * @throws NodeNotFoundException
     * @throws EphemeralOnEphemeralException
     */
    public function create(
        string $path,
        ?string $data = null,
        CreateMode $mode = CreateMode::PERSISTENT,
        int $zxid = 1,
        int $sessionId = 0
    ): string {
        $cleanPath = $this->normalizePath($path);
        if ($cleanPath === '/') {
            throw new NodeExistsException('/');
        }

        $parts = explode('/', trim($cleanPath, '/'));
        $nodeName = array_pop($parts);
        $parentPath = empty($parts) ? '/' : '/' . implode('/', $parts);

        $parent = $this->getNode($parentPath);
        if ($parent === null) {
            // Auto-create persistent parent paths like mkdir -p
            $this->create($parentPath, null, CreateMode::PERSISTENT, $zxid, $sessionId);
            $parent = $this->getNode($parentPath);
        }

        if ($parent->isEphemeral()) {
            throw new EphemeralOnEphemeralException($cleanPath);
        }

        // Handle Sequential naming
        if ($mode->isSequential()) {
            $nodeName .= $parent->getNextSequentialId();
            $cleanPath = ($parentPath === '/' ? '' : $parentPath) . '/' . $nodeName;
        }

        if (isset($this->nodesByPath[$cleanPath])) {
            throw new NodeExistsException($cleanPath);
        }

        $stat = new Stat(
            czxid: $zxid,
            mzxid: $zxid,
            ctime: time(),
            mtime: time(),
            version: 0,
            cversion: 0,
            ephemeralOwner: $mode->isEphemeral() ? $sessionId : 0,
            dataLength: $data !== null ? strlen($data) : 0,
            numChildren: 0
        );

        $znode = new ZNode($cleanPath, $data, $mode, $stat);
        $parent->addChild($znode);
        $this->nodesByPath[$cleanPath] = $znode;

        // Trigger Watchers: NodeCreated on target, NodeChildrenChanged on parent
        $this->watchManager->trigger(WatcherEvent::NODE_CREATED, $cleanPath);
        $this->watchManager->trigger(WatcherEvent::NODE_CHILDREN_CHANGED, $parentPath);

        return $cleanPath;
    }

    /**
     * Check if a node exists.
     */
    public function exists(string $path, ?callable $watchCallback = null, int $sessionId = 0): ?Stat
    {
        $cleanPath = $this->normalizePath($path);

        if ($watchCallback !== null) {
            $this->watchManager->addExistsWatch($cleanPath, $watchCallback, $sessionId);
        }

        $node = $this->getNode($cleanPath);
        return $node?->getStat();
    }

    /**
     * Get data of a node.
     *
     * @throws NodeNotFoundException
     */
    public function getData(string $path, ?callable $watchCallback = null, ?Stat &$stat = null, int $sessionId = 0): ?string
    {
        $cleanPath = $this->normalizePath($path);
        $node = $this->getNode($cleanPath);

        if ($node === null) {
            throw new NodeNotFoundException($cleanPath);
        }

        if ($watchCallback !== null) {
            $this->watchManager->addDataWatch($cleanPath, $watchCallback, $sessionId);
        }

        $stat = $node->getStat();
        return $node->getData();
    }

    /**
     * Set data for an existing node.
     *
     * @throws NodeNotFoundException
     */
    public function setData(string $path, ?string $data, int $version = -1, int $zxid = 1): Stat
    {
        $cleanPath = $this->normalizePath($path);
        $node = $this->getNode($cleanPath);

        if ($node === null) {
            throw new NodeNotFoundException($cleanPath);
        }

        if ($version !== -1 && $node->getStat()->version !== $version) {
            throw new \RuntimeException("KeeperErrorCode = BadVersion for {$cleanPath}");
        }

        $node->setData($data, $zxid);

        // Trigger Watcher: NodeDataChanged
        $this->watchManager->trigger(WatcherEvent::NODE_DATA_CHANGED, $cleanPath);

        return $node->getStat();
    }

    /**
     * Get children names of a node.
     *
     * @return array<string>
     * @throws NodeNotFoundException
     */
    public function getChildren(string $path, ?callable $watchCallback = null, int $sessionId = 0): array
    {
        $cleanPath = $this->normalizePath($path);
        $node = $this->getNode($cleanPath);

        if ($node === null) {
            throw new NodeNotFoundException($cleanPath);
        }

        if ($watchCallback !== null) {
            $this->watchManager->addChildrenWatch($cleanPath, $watchCallback, $sessionId);
        }

        return $node->getChildrenNames();
    }

    /**
     * Delete a node from the hierarchy.
     *
     * @throws NodeNotFoundException
     * @throws NotEmptyException
     */
    public function delete(string $path, int $version = -1, int $zxid = 1): bool
    {
        $cleanPath = $this->normalizePath($path);
        if ($cleanPath === '/') {
            throw new \RuntimeException("Cannot delete root node /");
        }

        $node = $this->getNode($cleanPath);
        if ($node === null) {
            throw new NodeNotFoundException($cleanPath);
        }

        if ($version !== -1 && $node->getStat()->version !== $version) {
            throw new \RuntimeException("KeeperErrorCode = BadVersion for {$cleanPath}");
        }

        if ($node->hasChildren()) {
            throw new NotEmptyException($cleanPath);
        }

        $parent = $node->getParent();
        $parentPath = $parent ? $parent->getPath() : '/';

        if ($parent !== null) {
            $parent->removeChild($node->getName());
        }

        unset($this->nodesByPath[$cleanPath]);

        // Trigger Watchers: NodeDeleted on target, NodeChildrenChanged on parent
        $this->watchManager->trigger(WatcherEvent::NODE_DELETED, $cleanPath);
        $this->watchManager->trigger(WatcherEvent::NODE_CHILDREN_CHANGED, $parentPath);

        return true;
    }

    public function getNode(string $path): ?ZNode
    {
        $cleanPath = $this->normalizePath($path);
        return $this->nodesByPath[$cleanPath] ?? null;
    }

    /**
     * Count total nodes currently in data tree.
     */
    public function countNodes(): int
    {
        return count($this->nodesByPath);
    }
}
