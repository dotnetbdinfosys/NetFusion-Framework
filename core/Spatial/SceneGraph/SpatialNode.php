<?php

declare(strict_types=1);

/**
 * NetFusion Framework - Enterprise Spatial Computing & WebXR Hyper-Spatial Digital Twin
 *
 * @author  Md. Atiquzzaman Ashish <atiquzzaman.ashish@dotnetbd.com>
 * @company DotNetBD
 * @link    https://www.dotnetbd.com
 * @license MIT
 */

namespace NetFusion\Core\Spatial\SceneGraph;

use JsonSerializable;
use NetFusion\Core\Spatial\Enums\NodeType;

class SpatialNode implements JsonSerializable
{
    protected ?SpatialNode $parent = null;
    /** @var array<string, SpatialNode> */
    protected array $children = [];
    protected Transform3D $transform;
    protected array $metadata = [];

    public function __construct(
        protected string $id,
        protected string $name = '',
        protected NodeType $type = NodeType::GROUP,
        ?Transform3D $transform = null,
        protected bool $visible = true
    ) {
        $this->transform = $transform ?? new Transform3D();
        if ($this->name === '') {
            $this->name = $id;
        }
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): NodeType
    {
        return $this->type;
    }

    public function getTransform(): Transform3D
    {
        return $this->transform;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function setVisible(bool $visible): void
    {
        $this->visible = $visible;
    }

    public function getParent(): ?SpatialNode
    {
        return $this->parent;
    }

    public function setParent(?SpatialNode $parent): void
    {
        $this->parent = $parent;
    }

    public function addChild(SpatialNode $child): self
    {
        $child->setParent($this);
        $this->children[$child->getId()] = $child;
        return $this;
    }

    public function removeChild(string $childId): ?SpatialNode
    {
        if (isset($this->children[$childId])) {
            $child = $this->children[$childId];
            $child->setParent(null);
            unset($this->children[$childId]);
            return $child;
        }
        return null;
    }

    public function getChild(string $childId): ?SpatialNode
    {
        return $this->children[$childId] ?? null;
    }

    /**
     * @return SpatialNode[]
     */
    public function getChildren(): array
    {
        return array_values($this->children);
    }

    public function setMetadata(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
    }

    public function getMetadata(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->metadata;
        }
        return $this->metadata[$key] ?? null;
    }

    /**
     * Propagate transform updates down the scene hierarchy
     */
    public function updateWorldTransforms(): void
    {
        $parentMatrix = $this->parent?->getTransform()->getWorldMatrix();
        $this->transform->updateMatrices($parentMatrix);

        foreach ($this->children as $child) {
            $child->updateWorldTransforms();
        }
    }

    public function toArray(): array
    {
        $childArr = [];
        foreach ($this->children as $child) {
            $childArr[] = $child->toArray();
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type->value,
            'visible' => $this->visible,
            'transform' => $this->transform->toArray(),
            'metadata' => $this->metadata,
            'children' => $childArr,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
