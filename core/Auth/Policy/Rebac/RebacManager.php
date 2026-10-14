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

namespace NetFusion\Core\Auth\Policy\Rebac;

use NetFusion\Core\Auth\Policy\Contracts\RebacStoreInterface;
use NetFusion\Core\Auth\Policy\Store\ArrayRebacStore;

class RebacManager
{
    protected RebacGraphResolver $resolver;

    public function __construct(
        protected ?RebacStoreInterface $store = null
    ) {
        $this->store = $store ?? new ArrayRebacStore();
        $this->resolver = new RebacGraphResolver($this->store);
    }

    public function getStore(): RebacStoreInterface
    {
        return $this->store;
    }

    public function setStore(RebacStoreInterface $store): self
    {
        $this->store = $store;
        $this->resolver->setStore($store);
        return $this;
    }

    public function getResolver(): RebacGraphResolver
    {
        return $this->resolver;
    }

    /**
     * Define relationship schema for an entity type or multiple entity types.
     *
     * @param string|array<string, mixed> $entityTypeOrSchema
     * @param array<string, mixed> $relations
     * @return self
     */
    public function schema(string|array $entityTypeOrSchema, array $relations = []): self
    {
        if (is_array($entityTypeOrSchema)) {
            foreach ($entityTypeOrSchema as $entityType => $rels) {
                $this->resolver->defineSchema((string)$entityType, (array)$rels);
            }
            return $this;
        }

        $this->resolver->defineSchema($entityTypeOrSchema, $relations);
        return $this;
    }

    /**
     * Write or register a relationship tuple.
     */
    public function write(string|RebacTuple $tuple): self
    {
        $t = is_string($tuple) ? RebacTuple::parse($tuple) : $tuple;
        $this->store->write($t);
        return $this;
    }

    /**
     * Delete a relationship tuple.
     */
    public function delete(string|RebacTuple $tuple): self
    {
        $t = is_string($tuple) ? RebacTuple::parse($tuple) : $tuple;
        $this->store->delete($t);
        return $this;
    }

    /**
     * Check if a subject has a relation on an entity.
     *
     * @param string $entity (e.g. "document:45")
     * @param string $relation (e.g. "viewer", "editor")
     * @param string $subject (e.g. "user:101")
     * @return bool
     */
    public function check(string $entity, string $relation, string $subject): bool
    {
        $visited = [];
        return $this->resolver->check($entity, $relation, $subject, $visited);
    }

    /**
     * Query tuples for an entity.
     *
     * @return RebacTuple[]
     */
    public function getTuplesForEntity(string $entityType, string $entityId): array
    {
        return $this->store->read(entityType: $entityType, entityId: $entityId);
    }

    /**
     * Query tuples for a subject.
     *
     * @return RebacTuple[]
     */
    public function getTuplesForSubject(string $subjectType, string $subjectId): array
    {
        return $this->store->read(subjectType: $subjectType, subjectId: $subjectId);
    }
}
