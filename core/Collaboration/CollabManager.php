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

namespace NetFusion\Core\Collaboration;

use NetFusion\Core\Collaboration\Document\Contracts\DocumentStoreInterface;
use NetFusion\Core\Collaboration\Document\DocumentSession;
use NetFusion\Core\Collaboration\Document\DocumentSnapshot;
use NetFusion\Core\Collaboration\Document\Store\ArrayDocumentStore;
use NetFusion\Core\Collaboration\Document\Store\DatabaseDocumentStore;
use NetFusion\Core\Collaboration\Ot\TextOperation;
use NetFusion\Core\Collaboration\WebRtc\RoomManager;
use NetFusion\Core\Collaboration\WebRtc\SignalingServer;

class CollabManager
{
    protected DocumentStoreInterface $store;
    protected SignalingServer $signaling;
    protected RoomManager $rooms;

    /** @var array<string, DocumentSession> */
    protected array $activeSessions = [];

    public function __construct(
        ?DocumentStoreInterface $store = null,
        ?SignalingServer $signaling = null,
        ?RoomManager $rooms = null
    ) {
        $this->store = $store ?? $this->resolveDefaultStore();
        $this->rooms = $rooms ?? new RoomManager();
        $this->signaling = $signaling ?? new SignalingServer($this->rooms);
    }

    public function getStore(): DocumentStoreInterface
    {
        return $this->store;
    }

    public function setStore(DocumentStoreInterface $store): self
    {
        $this->store = $store;
        return $this;
    }

    public function signaling(): SignalingServer
    {
        return $this->signaling;
    }

    public function rooms(): RoomManager
    {
        return $this->rooms;
    }

    /**
     * Create a new collaborative document.
     */
    public function createDocument(string $docId, string $title = 'Untitled Document', string $initialContent = '', ?string $authorId = null): DocumentSnapshot
    {
        $snapshot = new DocumentSnapshot(
            id: $docId,
            title: $title,
            content: $initialContent,
            revision: 0,
            authorId: $authorId
        );

        $this->store->save($snapshot);
        return $snapshot;
    }

    /**
     * Load document snapshot by ID.
     */
    public function document(string $docId): ?DocumentSnapshot
    {
        if (isset($this->activeSessions[$docId])) {
            return $this->activeSessions[$docId]->getSnapshot();
        }

        return $this->store->load($docId);
    }

    /**
     * Get or create active real-time editing session for a document.
     */
    public function session(string $docId): DocumentSession
    {
        if (isset($this->activeSessions[$docId])) {
            return $this->activeSessions[$docId];
        }

        $snapshot = $this->store->load($docId);
        if (!$snapshot) {
            $snapshot = $this->createDocument($docId);
        }

        $session = new DocumentSession($snapshot, $this->store);
        $this->activeSessions[$docId] = $session;

        return $session;
    }

    /**
     * Check if a document session is currently loaded in memory.
     */
    public function hasActiveSession(string $docId): bool
    {
        return isset($this->activeSessions[$docId]);
    }

    /**
     * Get list of all active session IDs.
     *
     * @return string[]
     */
    public function getActiveSessionIds(): array
    {
        return array_keys($this->activeSessions);
    }

    /**
     * Helper to create a fluent TextOperation builder.
     */
    public function operation(array $ops = []): TextOperation
    {
        return new TextOperation($ops);
    }

    protected function resolveDefaultStore(): DocumentStoreInterface
    {
        if (function_exists('app') && app()->has('db')) {
            try {
                return new DatabaseDocumentStore(app('db'));
            } catch (\Throwable) {
                // Fallback to in-memory store
            }
        }

        return new ArrayDocumentStore();
    }
}
