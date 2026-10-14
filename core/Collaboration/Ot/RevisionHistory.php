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

namespace NetFusion\Core\Collaboration\OT;

use InvalidArgumentException;

/**
 * Class RevisionHistory
 *
 * Server-side authoritative revision log and operational transformation coordinator.
 * Resolves stale client operations by rebasing them against missing revisions.
 */
class RevisionHistory
{
    private string $document;

    /** @var array<int, array{revision: int, peerId: string, op: TextOperation, timestamp: float}> */
    private array $history = [];

    private int $currentRevision = 0;

    public function __construct(string $initialDocument = '')
    {
        $this->document = $initialDocument;
    }

    public function getDocument(): string
    {
        return $this->document;
    }

    public function getCurrentRevision(): int
    {
        return $this->currentRevision;
    }

    /**
     * Submit and commit a client operation originating from $clientRevision.
     *
     * @param int $clientRevision The base revision known by client when op was created
     * @param TextOperation $clientOp The raw client operation
     * @param string $peerId Identifier of the submitting peer
     * @return array{revision: int, op: TextOperation} The committed revision and transformed operation
     */
    public function commit(int $clientRevision, TextOperation $clientOp, string $peerId = 'client-1'): array
    {
        if ($clientRevision < 0 || $clientRevision > $this->currentRevision) {
            throw new InvalidArgumentException("Invalid client revision ({$clientRevision}). Current server revision is {$this->currentRevision}.");
        }

        $transformedOp = $clientOp;

        // Rebase clientOp against all operations committed since $clientRevision
        for ($rev = $clientRevision; $rev < $this->currentRevision; $rev++) {
            $serverCommit = $this->history[$rev] ?? null;
            if ($serverCommit !== null) {
                $serverOp = $serverCommit['op'];
                // Transform: clientOp vs serverOp
                [$rebasedClientOp, $rebasedServerOp] = OperationalTransform::transform($transformedOp, $serverOp);
                $transformedOp = $rebasedClientOp;
            }
        }

        // Apply transformed operation to server document
        $this->document = $transformedOp->apply($this->document);

        $newRevision = ++$this->currentRevision;
        $this->history[$newRevision - 1] = [
            'revision' => $newRevision,
            'peerId' => $peerId,
            'op' => $transformedOp,
            'timestamp' => microtime(true),
        ];

        return [
            'revision' => $newRevision,
            'op' => $transformedOp,
        ];
    }

    /**
     * Retrieve all operations committed strictly after $revision.
     *
     * @return array<int, array{revision: int, peerId: string, op: TextOperation, timestamp: float}>
     */
    public function getOperationsSince(int $revision): array
    {
        $res = [];
        for ($r = $revision; $r < $this->currentRevision; $r++) {
            if (isset($this->history[$r])) {
                $res[] = $this->history[$r];
            }
        }
        return $res;
    }

    /**
     * Get the full history log.
     */
    public function getHistory(): array
    {
        return $this->history;
    }
}
