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

namespace NetFusion\Core\Auth\Policy\Store;

use NetFusion\Core\Auth\Policy\Contracts\RebacStoreInterface;
use NetFusion\Core\Auth\Policy\Rebac\RebacTuple;

class ArrayRebacStore implements RebacStoreInterface
{
    /** @var array<string, RebacTuple> */
    protected array $tuples = [];

    /**
     * @param RebacTuple[] $tuples
     */
    public function __construct(array $tuples = [])
    {
        foreach ($tuples as $t) {
            $this->write($t);
        }
    }

    public function write(RebacTuple $tuple): void
    {
        $this->tuples[$tuple->toString()] = $tuple;
    }

    public function delete(RebacTuple $tuple): void
    {
        unset($this->tuples[$tuple->toString()]);
    }

    public function read(
        ?string $entityType = null,
        ?string $entityId = null,
        ?string $relation = null,
        ?string $subjectType = null,
        ?string $subjectId = null
    ): array {
        $results = [];

        foreach ($this->tuples as $tuple) {
            if ($entityType !== null && $tuple->entityType !== $entityType) {
                continue;
            }
            if ($entityId !== null && $tuple->entityId !== $entityId) {
                continue;
            }
            if ($relation !== null && $tuple->relation !== $relation) {
                continue;
            }
            if ($subjectType !== null && $tuple->subjectType !== $subjectType) {
                continue;
            }
            if ($subjectId !== null && $tuple->subjectId !== $subjectId) {
                continue;
            }

            $results[] = $tuple;
        }

        return $results;
    }

    public function exists(RebacTuple $tuple): bool
    {
        return isset($this->tuples[$tuple->toString()]);
    }

    public function find(array $criteria = []): array
    {
        return $this->read(
            entityType: $criteria['entity_type'] ?? null,
            entityId: $criteria['entity_id'] ?? null,
            relation: $criteria['relation'] ?? null,
            subjectType: $criteria['subject_type'] ?? null,
            subjectId: $criteria['subject_id'] ?? null
        );
    }

    public function clear(): void
    {
        $this->tuples = [];
    }

    public function count(): int
    {
        return count($this->tuples);
    }
}
