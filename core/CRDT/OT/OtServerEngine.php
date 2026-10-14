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

namespace NetFusion\Core\CRDT\OT;

/**
 * Operational Transformation Central Server Engine.
 * Manages versioned document revisions, linearizes concurrent client operations,
 * and broadcasts transformed operations with updated revisions.
 */
class OtServerEngine
{
    private string $documentId;
    private string $content;
    private int $revision;
    /** @var array<int, TextOp> revisionNumber => TextOp */
    private array $history = [];

    public function __construct(string $documentId, string $initialContent = '', int $initialRevision = 0)
    {
        $this->documentId = $documentId;
        $this->content    = $initialContent;
        $this->revision   = $initialRevision;
    }

    public function getDocumentId(): string
    {
        return $this->documentId;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getRevision(): int
    {
        return $this->revision;
    }

    /**
     * Submit client operation based on client's known revision.
     * Transforms the operation against all intervening server revisions, commits it,
     * updates the document content, and increments server revision.
     *
     * @return array{transformed_op: TextOp, new_revision: int}
     */
    public function submitOperation(TextOp $clientOp, int $clientRevision): array
    {
        // 1. Gather all operations that were committed since client's revision
        $interveningOps = [];
        for ($r = $clientRevision + 1; $r <= $this->revision; $r++) {
            if (isset($this->history[$r])) {
                $interveningOps[] = $this->history[$r];
            }
        }

        // 2. Transform the client's operation against intervening history
        $transformedOp = OtTransformer::transformAgainstHistory($clientOp, $interveningOps);

        // 3. Apply transformed operation to server content
        $this->content = $transformedOp->applyTo($this->content);

        // 4. Commit to history
        $this->revision++;
        $this->history[$this->revision] = $transformedOp;

        return [
            'transformed_op' => $transformedOp,
            'new_revision'   => $this->revision,
        ];
    }

    public function getHistoryFrom(int $sinceRevision): array
    {
        $ops = [];
        for ($r = $sinceRevision + 1; $r <= $this->revision; $r++) {
            if (isset($this->history[$r])) {
                $ops[$r] = $this->history[$r]->toArray();
            }
        }
        return $ops;
    }
}
