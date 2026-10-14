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

class RebacGraphResolver
{
    /** @var array<string, array<string, array{computed_userset?: string, tuple_to_userset?: array{tupleset: string, computed_userset: string}}>> */
    protected array $schema = [];

    public function __construct(
        protected ?RebacStoreInterface $store = null
    ) {
        $this->store = $store ?? new ArrayRebacStore();
    }

    public function setStore(RebacStoreInterface $store): self
    {
        $this->store = $store;
        return $this;
    }

    public function getStore(): RebacStoreInterface
    {
        return $this->store;
    }

    /**
     * Define relationship schema (e.g. 'editor' implies 'viewer', or inheritance from 'parent').
     *
     * Example:
     *   $resolver->defineSchema('document', [
     *       'editor' => ['owner', 'parent.editor'],
     *       'viewer' => ['editor', 'parent.viewer'],
     *   ]);
     */
    public function defineSchema(string $entityType, array $relations): self
    {
        if (!isset($this->schema[$entityType])) {
            $this->schema[$entityType] = [];
        }

        foreach ($relations as $relation => $definition) {
            if (!isset($this->schema[$entityType][$relation])) {
                $this->schema[$entityType][$relation] = [
                    'computed_userset' => [],
                    'tuple_to_userset' => []
                ];
            }

            if (is_array($definition)) {
                if (isset($definition['computed_userset']) || isset($definition['tuple_to_userset'])) {
                    if (isset($definition['computed_userset'])) {
                        $this->schema[$entityType][$relation]['computed_userset'] = array_merge(
                            $this->schema[$entityType][$relation]['computed_userset'],
                            (array)$definition['computed_userset']
                        );
                    }
                    if (isset($definition['tuple_to_userset'])) {
                        $ttu = isset($definition['tuple_to_userset']['tupleset']) ? [$definition['tuple_to_userset']] : $definition['tuple_to_userset'];
                        $this->schema[$entityType][$relation]['tuple_to_userset'] = array_merge(
                            $this->schema[$entityType][$relation]['tuple_to_userset'],
                            $ttu
                        );
                    }
                } else {
                    foreach ($definition as $item) {
                        if (is_string($item)) {
                            if (str_contains($item, '.')) {
                                [$tupleset, $computed] = explode('.', $item, 2);
                                $this->schema[$entityType][$relation]['tuple_to_userset'][] = [
                                    'tupleset' => $tupleset,
                                    'computed_userset' => $computed,
                                ];
                            } else {
                                $this->schema[$entityType][$relation]['computed_userset'][] = $item;
                            }
                        }
                    }
                }
            } elseif (is_string($definition)) {
                if (str_contains($definition, '.')) {
                    [$tupleset, $computed] = explode('.', $definition, 2);
                    $this->schema[$entityType][$relation]['tuple_to_userset'][] = [
                        'tupleset' => $tupleset,
                        'computed_userset' => $computed,
                    ];
                } else {
                    $this->schema[$entityType][$relation]['computed_userset'][] = $definition;
                }
            }
        }

        return $this;
    }

    /**
     * Check if a subject has a relation on an entity using recursive Zanzibar graph traversal.
     *
     * @param string $entity ("document:doc_1", "folder:f_1")
     * @param string $relation ("viewer", "editor", "owner")
     * @param string $subject ("user:101" or "group:eng#member")
     * @param array<string, bool> $visited (Cycle detection)
     * @return bool
     */
    public function check(
        string $entity,
        string $relation,
        string $subject,
        array &$visited = []
    ): bool {
        // Auto-normalize if subject was passed first (e.g. check("user:101", "viewer", "doc:1"))
        if (str_starts_with($entity, 'user:') && !str_starts_with($subject, 'user:')) {
            $tmp = $entity;
            $entity = $subject;
            $subject = $tmp;
        }

        // Parse entity
        [$entityType, $entityId] = explode(':', $entity, 2);

        // Parse subject
        $subjectRelation = null;
        if (str_contains($subject, '#')) {
            [$subPart, $subjectRelation] = explode('#', $subject, 2);
            [$subjectType, $subjectId] = explode(':', $subPart, 2);
        } else {
            [$subjectType, $subjectId] = explode(':', $subject, 2);
        }

        $checkKey = "{$entityType}:{$entityId}#{$relation}@{$subjectType}:{$subjectId}" . ($subjectRelation ? "#{$subjectRelation}" : "");
        if (isset($visited[$checkKey])) {
            return false; // Cycle detected
        }
        $visited[$checkKey] = true;

        // 1. Direct Tuple Match: document:101#viewer@user:42
        $directTuples = $this->store->read(
            entityType: $entityType,
            entityId: $entityId,
            relation: $relation,
            subjectType: $subjectType,
            subjectId: $subjectId
        );

        if (!empty($directTuples)) {
            return true;
        }

        // 2. Userset Match: document:101#viewer@group:accounting#member
        // Check all tuples where entityType & entityId & relation match and subject has a subjectRelation
        $entityTuples = $this->store->read(
            entityType: $entityType,
            entityId: $entityId,
            relation: $relation
        );

        foreach ($entityTuples as $tuple) {
            if ($tuple->subjectRelation !== null) {
                // Recursively check if the requested subject has the required subjectRelation on the intermediate group/entity
                $intermediateEntity = "{$tuple->subjectType}:{$tuple->subjectId}";
                if ($this->check($intermediateEntity, $tuple->subjectRelation, $subject, $visited)) {
                    return true;
                }
            }
        }

        // 3. Schema Relations (Hierarchy, Implication, Computed Usersets & Tuple-to-Usersets)
        $entitySchema = $this->schema[$entityType] ?? [];
        if (isset($entitySchema[$relation])) {
            $rules = $entitySchema[$relation];

            // Computed Userset: e.g. viewer is implied by editor
            if (isset($rules['computed_userset'])) {
                $computed = (array)$rules['computed_userset'];
                foreach ($computed as $higherRelation) {
                    if ($this->check($entity, $higherRelation, $subject, $visited)) {
                        return true;
                    }
                }
            }

            // Tuple to userset: e.g. viewer on document is inherited from viewer on parent folder
            if (isset($rules['tuple_to_userset'])) {
                $ttuList = isset($rules['tuple_to_userset']['tupleset']) ? [$rules['tuple_to_userset']] : $rules['tuple_to_userset'];
                foreach ($ttuList as $ttu) {
                    $tuplesetRelation = $ttu['tupleset'] ?? '';
                    $targetRelation = $ttu['computed_userset'] ?? $relation;

                    $parentTuples = $this->store->read(
                        entityType: $entityType,
                        entityId: $entityId,
                        relation: $tuplesetRelation
                    );

                    foreach ($parentTuples as $pTuple) {
                        $parentEntity = "{$pTuple->subjectType}:{$pTuple->subjectId}";
                        if ($this->check($parentEntity, $targetRelation, $subject, $visited)) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }
}
