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

namespace NetFusion\Core\Collaboration\Document\Store;

use NetFusion\Core\Collaboration\Document\Contracts\DocumentStoreInterface;
use NetFusion\Core\Collaboration\Document\DocumentSnapshot;
use NetFusion\Core\Collaboration\Ot\TextOperation;

class ArrayDocumentStore implements DocumentStoreInterface
{
    /** @var array<string, DocumentSnapshot> */
    protected array $documents = [];

    /** @var array<string, array<int, array{revision: int, operation: TextOperation, user_id: string, created_at: string}>> */
    protected array $operations = [];

    public function load(string $docId): ?DocumentSnapshot
    {
        return $this->documents[$docId] ?? null;
    }

    public function save(DocumentSnapshot $doc): bool
    {
        $this->documents[$doc->id] = $doc;
        return true;
    }

    public function appendOperation(string $docId, int $revision, TextOperation $operation, string $userId): bool
    {
        if (!isset($this->operations[$docId])) {
            $this->operations[$docId] = [];
        }

        $this->operations[$docId][] = [
            'revision' => $revision,
            'operation' => $operation,
            'user_id' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        return true;
    }

    public function getOperationsSince(string $docId, int $fromRevision): array
    {
        $all = $this->operations[$docId] ?? [];
        $result = [];

        foreach ($all as $entry) {
            if ($entry['revision'] > $fromRevision) {
                $result[] = $entry;
            }
        }

        return $result;
    }

    public function exists(string $docId): bool
    {
        return isset($this->documents[$docId]);
    }

    public function delete(string $docId): bool
    {
        unset($this->documents[$docId], $this->operations[$docId]);
        return true;
    }
}
