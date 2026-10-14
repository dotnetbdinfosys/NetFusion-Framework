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

namespace NetFusion\Core\Collaboration\Document;

use InvalidArgumentException;
use NetFusion\Core\Collaboration\Document\Contracts\DocumentStoreInterface;
use NetFusion\Core\Collaboration\Ot\Cursor;
use NetFusion\Core\Collaboration\Ot\Selection;
use NetFusion\Core\Collaboration\Ot\TextOperation;

class DocumentSession
{
    protected DocumentSnapshot $snapshot;
    protected DocumentStoreInterface $store;

    /**
     * In-memory cache of operations history for fast concurrency transformation.
     *
     * @var array<int, array{revision: int, operation: TextOperation, user_id: string}>
     */
    protected array $operationsHistory = [];

    /**
     * Active connected user cursors.
     *
     * @var array<string, Cursor>
     */
    protected array $cursors = [];

    public function __construct(
        DocumentSnapshot $snapshot,
        DocumentStoreInterface $store
    ) {
        $this->snapshot = $snapshot;
        $this->store = $store;

        // Load historical operations since document creation
        $historical = $this->store->getOperationsSince($this->snapshot->id, 0);
        foreach ($historical as $h) {
            $this->operationsHistory[$h['revision']] = $h;
        }
    }

    public function getId(): string
    {
        return $this->snapshot->id;
    }

    public function getTitle(): string
    {
        return $this->snapshot->title;
    }

    public function getContent(): string
    {
        return $this->snapshot->content;
    }

    public function getRevision(): int
    {
        return $this->snapshot->revision;
    }

    public function getSnapshot(): DocumentSnapshot
    {
        return $this->snapshot;
    }

    /**
     * Apply a client operation at a given client revision with OT concurrency resolution.
     *
     * @return array{operation: TextOperation, revision: int}
     */
    public function applyClientOperation(int $clientRevision, TextOperation $operation, string $userId): array
    {
        if ($clientRevision > $this->snapshot->revision) {
            throw new InvalidArgumentException(
                "Client revision ({$clientRevision}) is ahead of server revision ({$this->snapshot->revision})."
            );
        }

        $transformedOp = $operation;

        // Catch-up transformation if client was behind
        if ($clientRevision < $this->snapshot->revision) {
            for ($rev = $clientRevision + 1; $rev <= $this->snapshot->revision; $rev++) {
                if (!isset($this->operationsHistory[$rev])) {
                    // Fallback to fetch from persistent store if not in memory
                    $missing = $this->store->getOperationsSince($this->snapshot->id, $rev - 1);
                    foreach ($missing as $m) {
                        $this->operationsHistory[$m['revision']] = $m;
                    }
                }

                if (isset($this->operationsHistory[$rev])) {
                    $serverOp = $this->operationsHistory[$rev]['operation'];
                    [$transformedOp, $serverOpPrime] = TextOperation::transformOperations($transformedOp, $serverOp, 'left');
                }
            }
        }

        // Apply transformed operation to server document
        $this->snapshot->content = $transformedOp->apply($this->snapshot->content);
        $this->snapshot->revision++;
        $newRevision = $this->snapshot->revision;

        // Record to memory history & persistence store
        $record = [
            'revision' => $newRevision,
            'operation' => $transformedOp,
            'user_id' => $userId,
        ];
        $this->operationsHistory[$newRevision] = $record;
        $this->store->appendOperation($this->snapshot->id, $newRevision, $transformedOp, $userId);
        $this->store->save($this->snapshot);

        // Adjust all active user cursors
        foreach ($this->cursors as $cursor) {
            $cursor->transform($transformedOp);
        }

        return [
            'operation' => $transformedOp,
            'revision' => $newRevision,
        ];
    }

    /**
     * Update or register an active user cursor in this session.
     */
    public function updateCursor(string $userId, string $username, string $color, Selection $selection): Cursor
    {
        $cursor = new Cursor($userId, $username, $color, $selection);
        $this->cursors[$userId] = $cursor;
        return $cursor;
    }

    /**
     * Remove an inactive user cursor.
     */
    public function removeCursor(string $userId): void
    {
        unset($this->cursors[$userId]);
    }

    /**
     * @return array<string, Cursor>
     */
    public function getCursors(): array
    {
        return $this->cursors;
    }

    public function getCursor(string $userId): ?Cursor
    {
        return $this->cursors[$userId] ?? null;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->snapshot->id,
            'title' => $this->snapshot->title,
            'content' => $this->snapshot->content,
            'revision' => $this->snapshot->revision,
            'cursors' => array_map(fn(Cursor $c) => $c->toArray(), $this->cursors),
        ];
    }
}
