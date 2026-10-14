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

class SceneGraph implements JsonSerializable
{
    protected SpatialNode $root;
    /** @var array<string, SpatialNode> */
    protected array $nodeIndex = [];

    public function __construct(protected string $id, protected string $name = 'Root Scene')
    {
        $this->root = new SpatialNode('root', $this->name, NodeType::GROUP);
        $this->nodeIndex['root'] = $this->root;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRoot(): SpatialNode
    {
        return $this->root;
    }

    public function addNode(SpatialNode $node, ?string $parentId = null): self
    {
        $targetParent = $parentId !== null ? $this->getNode($parentId) : $this->root;
        if ($targetParent === null) {
            $targetParent = $this->root;
        }

        $targetParent->addChild($node);
        $this->indexNode($node);
        $this->update();
        return $this;
    }

    public function getNode(string $id): ?SpatialNode
    {
        return $this->nodeIndex[$id] ?? null;
    }

    public function removeNode(string $id): ?SpatialNode
    {
        if ($id === 'root' || !isset($this->nodeIndex[$id])) {
            return null;
        }

        $node = $this->nodeIndex[$id];
        $parent = $node->getParent();
        $parent?->removeChild($id);

        $this->unindexNode($node);
        $this->update();
        return $node;
    }

    public function count(): int
    {
        return count($this->nodeIndex);
    }

    public function update(): void
    {
        $this->root->updateWorldTransforms();
    }

    protected function indexNode(SpatialNode $node): void
    {
        $this->nodeIndex[$node->getId()] = $node;
        foreach ($node->getChildren() as $child) {
            $this->indexNode($child);
        }
    }

    protected function unindexNode(SpatialNode $node): void
    {
        unset($this->nodeIndex[$node->getId()]);
        foreach ($node->getChildren() as $child) {
            $this->unindexNode($child);
        }
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'totalNodes' => $this->count(),
            'root' => $this->root->toArray(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
