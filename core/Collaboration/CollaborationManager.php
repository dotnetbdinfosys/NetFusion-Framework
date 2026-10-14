<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | Author    : Md. Atiquzzaman Ashish
 | Company   : DotNetBD
 | Website   : www.dotnetbd.com
 | Copyright : © DotNetBD. All rights reserved.
 | License   : MIT
 | Version   : 1.0.0
 |
 |--------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace NetFusion\Core\Collaboration;

use NetFusion\Core\Collaboration\Session\CollaborativeDocument;
use NetFusion\Core\Collaboration\Session\DocumentSnapshot;
use NetFusion\Core\Collaboration\WebRTC\SignalingRoom;
use NetFusion\Core\Collaboration\WebRTC\PeerMesh;

/**
 * Class CollaborationManager
 *
 * Master central facade orchestrating real-time collaborative documents,
 * WebRTC signaling rooms, P2P mesh network topology, and persistent snapshots.
 */
class CollaborationManager
{
    private static ?self $instance = null;

    /** @var array<string, CollaborativeDocument> */
    private array $documents = [];

    /** @var array<string, SignalingRoom> */
    private array $rooms = [];

    /** @var array<string, DocumentSnapshot[]> */
    private array $snapshots = [];

    private PeerMesh $mesh;
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'presence_timeout' => 30.0,
            'snapshot_interval_revisions' => 50,
            'default_room' => 'general',
        ], $config);

        $this->mesh = new PeerMesh();
    }

    public static function getInstance(array $config = []): self
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    public function getMesh(): PeerMesh
    {
        return $this->mesh;
    }

    /**
     * Get or create a collaborative document.
     */
    public function getOrCreateDocument(string $docId, string $peerId = 'local-peer', string $initialText = ''): CollaborativeDocument
    {
        if (!isset($this->documents[$docId])) {
            $doc = new CollaborativeDocument($docId, $peerId, $initialText);
            // Check if existing snapshot is available
            $latest = $this->loadLatestSnapshot($docId);
            if ($latest !== null) {
                $doc->restoreFromSnapshot($latest);
            }
            $this->documents[$docId] = $doc;
        }

        return $this->documents[$docId];
    }

    public function hasDocument(string $docId): bool
    {
        return isset($this->documents[$docId]);
    }

    public function getDocument(string $docId): ?CollaborativeDocument
    {
        return $this->documents[$docId] ?? null;
    }

    /**
     * @return array<string, CollaborativeDocument>
     */
    public function getAllDocuments(): array
    {
        return $this->documents;
    }

    /**
     * Get or create a WebRTC signaling room.
     */
    public function getOrCreateRoom(string $roomId): SignalingRoom
    {
        if (!isset($this->rooms[$roomId])) {
            $this->rooms[$roomId] = new SignalingRoom($roomId);
        }
        return $this->rooms[$roomId];
    }

    public function hasRoom(string $roomId): bool
    {
        return isset($this->rooms[$roomId]);
    }

    /**
     * @return array<string, SignalingRoom>
     */
    public function getAllRooms(): array
    {
        return $this->rooms;
    }

    /**
     * Persist an immutable document snapshot.
     */
    public function saveSnapshot(DocumentSnapshot $snapshot): void
    {
        if (!isset($this->snapshots[$snapshot->docId])) {
            $this->snapshots[$snapshot->docId] = [];
        }
        $this->snapshots[$snapshot->docId][] = $snapshot;
    }

    /**
     * Load the latest snapshot for a document.
     */
    public function loadLatestSnapshot(string $docId): ?DocumentSnapshot
    {
        $snaps = $this->snapshots[$docId] ?? [];
        if (empty($snaps)) {
            return null;
        }
        return $snaps[count($snaps) - 1];
    }

    /**
     * Prune inactive presences across all active documents.
     *
     * @return int Total number of pruned peers
     */
    public function pruneStalePresences(?float $timeout = null): int
    {
        $ttl = $timeout ?? (float) ($this->config['presence_timeout'] ?? 30.0);
        $prunedCount = 0;

        foreach ($this->documents as $doc) {
            $active = $doc->getActivePresences($ttl);
            // Reflect difference
            // Document presences array cleanup
            $refProp = (new \ReflectionClass($doc))->getProperty('presences');
            $refProp->setAccessible(true);
            /** @var array<string, \NetFusion\Core\Collaboration\Session\CursorPresence> $allPresences */
            $allPresences = $refProp->getValue($doc);

            foreach ($allPresences as $pId => $presence) {
                if (!isset($active[$pId])) {
                    $doc->removePresence($pId);
                    $prunedCount++;
                }
            }
        }

        return $prunedCount;
    }
}
