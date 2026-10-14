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

namespace NetFusion\Core\Collaboration\Document\Contracts;

use NetFusion\Core\Collaboration\Document\DocumentSnapshot;
use NetFusion\Core\Collaboration\Ot\TextOperation;

interface DocumentStoreInterface
{
    /**
     * Load document snapshot by ID.
     */
    public function load(string $docId): ?DocumentSnapshot;

    /**
     * Save/update document snapshot.
     */
    public function save(DocumentSnapshot $doc): bool;

    /**
     * Append an applied OT operation to the document changelog stream.
     */
    public function appendOperation(string $docId, int $revision, TextOperation $operation, string $userId): bool;

    /**
     * Get list of operations since a specific revision.
     *
     * @return array<int, array{revision: int, operation: TextOperation, user_id: string, created_at: string}>
     */
    public function getOperationsSince(string $docId, int $fromRevision): array;

    /**
     * Check if a document exists.
     */
    public function exists(string $docId): bool;

    /**
     * Delete document and its operation history.
     */
    public function delete(string $docId): bool;
}
