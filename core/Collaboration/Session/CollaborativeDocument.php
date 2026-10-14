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

namespace NetFusion\Core\Collaboration\Session;

use NetFusion\Core\Collaboration\CRDT\RgaText;
use NetFusion\Core\Collaboration\CRDT\LwwMap;
use NetFusion\Core\Collaboration\OT\RevisionHistory;
use NetFusion\Core\Collaboration\OT\TextOperation;

/**
 * Class CollaborativeDocument
 *
 * Unified multi-layered collaborative document model.
 * Seamlessly integrates CRDT text editing (RGA), object metadata (LWW-Map),
 * server-side OT revision logging, and real-time cursor presence awareness.
 */
class CollaborativeDocument
{
    private RgaText $textCrdt;
    private LwwMap $metadata;
    private RevisionHistory $revisionHistory;

    /** @var array<string, CursorPresence> */
    private array $presences = [];

    private int $revision = 0;

    public function __construct(
        public readonly string $docId,
        public readonly string $localPeerId = 'peer-1',
        string $initialText = ''
    ) {
        $this->textCrdt = new RgaText($localPeerId);
        $this->metadata = new LwwMap($localPeerId);
        $this->revisionHistory = new RevisionHistory($initialText);

        if ($initialText !== '') {
            $this->textCrdt->insert(0, $initialText);
        }
    }

    public function getDocId(): string
    {
        return $this->docId;
    }

    public function getLocalPeerId(): string
    {
        return $this->localPeerId;
    }

    public function getRevision(): int
    {
        return $this->revision;
    }

    public function getTextCrdt(): RgaText
    {
        return $this->textCrdt;
    }

    public function getMetadata(): LwwMap
    {
        return $this->metadata;
    }

    public function getRevisionHistory(): RevisionHistory
    {
        return $this->revisionHistory;
    }

    /**
     * Insert text at given visible character index.
     */
    public function insertText(int $index, string $text): void
    {
        $this->textCrdt->insert($index, $text);
        $this->revision++;
    }

    /**
     * Delete characters starting at visible character index.
     */
    public function deleteText(int $index, int $length = 1): void
    {
        $this->textCrdt->delete($index, $length);
        $this->revision++;
    }

    /**
     * Get materialized visible text.
     */
    public function getText(): string
    {
        return $this->textCrdt->getText();
    }

    public function length(): int
    {
        return $this->textCrdt->length();
    }

    /**
     * Set a metadata property.
     */
    public function setProperty(string $key, mixed $value): void
    {
        $this->metadata->set($key, $value);
        $this->revision++;
    }

    /**
     * Get a metadata property.
     */
    public function getProperty(string $key, mixed $default = null): mixed
    {
        return $this->metadata->get($key, $default);
    }

    public function getAllProperties(): array
    {
        return $this->metadata->all();
    }

    /**
     * Update live presence awareness for a peer.
     */
    public function updatePresence(
        string $peerId,
        string $userName,
        int $cursorIndex,
        ?int $selectionEnd = null,
        string $color = '#3b82f6'
    ): void {
        if (!isset($this->presences[$peerId])) {
            $this->presences[$peerId] = new CursorPresence($peerId, $userName, $cursorIndex, $selectionEnd ?? $cursorIndex, $color);
        } else {
            $this->presences[$peerId]->updateCursor($cursorIndex, $selectionEnd);
        }
    }

    public function removePresence(string $peerId): void
    {
        unset($this->presences[$peerId]);
    }

    /**
     * @return array<string, CursorPresence>
     */
    public function getActivePresences(float $timeoutSeconds = 30.0): array
    {
        $active = [];
        foreach ($this->presences as $peerId => $presence) {
            if ($presence->isAlive($timeoutSeconds)) {
                $active[$peerId] = $presence;
            }
        }
        return $active;
    }

    /**
     * Merge remote document CRDT state into this document.
     */
    public function merge(CollaborativeDocument $other): void
    {
        $this->textCrdt->merge($other->textCrdt);
        $this->metadata->merge($other->metadata);
        $this->revision = max($this->revision, $other->revision) + 1;
    }

    /**
     * Generate an immutable snapshot of the document state.
     */
    public function createSnapshot(): DocumentSnapshot
    {
        return new DocumentSnapshot(
            docId: $this->docId,
            revision: $this->revision,
            text: $this->getText(),
            metadata: $this->getAllProperties(),
            crdtState: [
                'text' => $this->textCrdt->toArray(),
                'metadata' => $this->metadata->toArray(),
            ],
            timestamp: microtime(true)
        );
    }

    /**
     * Restore document state from a snapshot.
     */
    public function restoreFromSnapshot(DocumentSnapshot $snapshot): void
    {
        $this->revision = $snapshot->revision;
        if (!empty($snapshot->crdtState['text'])) {
            $this->textCrdt = RgaText::fromArray($snapshot->crdtState['text']);
        } else {
            $this->textCrdt = new RgaText($this->localPeerId);
            if ($snapshot->text !== '') {
                $this->textCrdt->insert(0, $snapshot->text);
            }
        }

        if (!empty($snapshot->crdtState['metadata'])) {
            $this->metadata = LwwMap::fromArray($snapshot->crdtState['metadata']);
        } else {
            $this->metadata = new LwwMap($this->localPeerId);
            foreach ($snapshot->metadata as $k => $v) {
                $this->metadata->set($k, $v);
            }
        }
    }
}
