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

use JsonSerializable;

class RebacTuple implements JsonSerializable
{
    /**
     * @param string $entityType (e.g. 'document', 'folder', 'tenant')
     * @param string $entityId   (e.g. '101', 'finance_folder')
     * @param string $relation   (e.g. 'viewer', 'editor', 'owner', 'parent', 'member')
     * @param string $subjectType (e.g. 'user', 'group', 'service')
     * @param string $subjectId   (e.g. '42', 'accounting')
     * @param string|null $subjectRelation (e.g. 'member' for usersets / group membership)
     */
    public function __construct(
        public string $entityType,
        public string $entityId,
        public string $relation,
        public string $subjectType,
        public string $subjectId,
        public ?string $subjectRelation = null
    ) {
    }

    public static function make(
        string $entityType,
        string $entityId,
        string $relation,
        string $subjectType,
        string $subjectId,
        ?string $subjectRelation = null
    ): self {
        return new self($entityType, $entityId, $relation, $subjectType, $subjectId, $subjectRelation);
    }

    /**
     * Parse string in Zanzibar notation: "document:101#viewer@user:42" or "folder:1#editor@group:marketing#member"
     */
    public static function parse(string $notation): self
    {
        // Format: entity_type:entity_id#relation@subject_type:subject_id[#subject_relation]
        if (!preg_match('/^([^:]+):([^#]+)#([^@]+)@([^:#]+):([^#]+)(?:#(.+))?$/', trim($notation), $matches)) {
            throw new \InvalidArgumentException("Invalid Zanzibar tuple notation format: [{$notation}]");
        }

        return new self(
            entityType: $matches[1],
            entityId: $matches[2],
            relation: $matches[3],
            subjectType: $matches[4],
            subjectId: $matches[5],
            subjectRelation: $matches[6] ?? null
        );
    }

    public static function fromString(string $notation): self
    {
        return self::parse($notation);
    }

    public function getEntity(): string
    {
        return "{$this->entityType}:{$this->entityId}";
    }

    public function getSubject(): string
    {
        $sub = "{$this->subjectType}:{$this->subjectId}";
        if ($this->subjectRelation !== null) {
            $sub .= "#{$this->subjectRelation}";
        }
        return $sub;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function getRelation(): string
    {
        return $this->relation;
    }

    public function getSubjectType(): string
    {
        return $this->subjectType;
    }

    public function getSubjectId(): string
    {
        return $this->subjectId;
    }

    public function getSubjectRelation(): ?string
    {
        return $this->subjectRelation;
    }

    public function toString(): string
    {
        $str = "{$this->entityType}:{$this->entityId}#{$this->relation}@{$this->subjectType}:{$this->subjectId}";
        if ($this->subjectRelation !== null) {
            $str .= "#{$this->subjectRelation}";
        }
        return $str;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function matchesEntity(string $type, string $id): bool
    {
        return $this->entityType === $type && $this->entityId === $id;
    }

    public function matchesSubject(string $type, string $id): bool
    {
        return $this->subjectType === $type && $this->subjectId === $id;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'relation' => $this->relation,
            'subject_type' => $this->subjectType,
            'subject_id' => $this->subjectId,
            'subject_relation' => $this->subjectRelation,
            'notation' => $this->toString(),
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
