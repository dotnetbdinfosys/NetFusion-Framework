<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\ZooKeeper\Models;

use NetFusion\Core\ZooKeeper\Enums\CreateMode;

/**
 * Represents an individual node in the hierarchical ZooKeeper data tree.
 */
class ZNode
{
    protected string $path;
    protected string $name;
    protected ?string $data;
    protected CreateMode $mode;
    protected Stat $stat;
    /** @var array<string, ZNode> [childName => ZNode] */
    protected array $children = [];
    protected ?ZNode $parent = null;
    protected int $sequentialCounter = 0;

    public function __construct(string $path, ?string $data = null, CreateMode $mode = CreateMode::PERSISTENT, ?Stat $stat = null)
    {
        $this->path = '/' . trim($path, '/');
        if ($this->path === '/') {
            $this->name = '';
        } else {
            $parts = explode('/', $this->path);
            $this->name = end($parts);
        }

        $this->data = $data;
        $this->mode = $mode;
        $this->stat = $stat ?? new Stat(dataLength: $data !== null ? strlen($data) : 0);
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getData(): ?string
    {
        return $this->data;
    }

    public function setData(?string $data, int $zxid = 0): self
    {
        $this->data = $data;
        $this->stat->version++;
        $this->stat->mzxid = $zxid;
        $this->stat->mtime = time();
        $this->stat->dataLength = $data !== null ? strlen($data) : 0;
        return $this;
    }

    public function getMode(): CreateMode
    {
        return $this->mode;
    }

    public function getStat(): Stat
    {
        $this->stat->numChildren = count($this->children);
        return $this->stat;
    }

    public function isEphemeral(): bool
    {
        return $this->mode->isEphemeral();
    }

    public function getParent(): ?ZNode
    {
        return $this->parent;
    }

    public function setParent(?ZNode $parent): self
    {
        $this->parent = $parent;
        return $this;
    }

    public function addChild(ZNode $child): self
    {
        $childName = $child->getName();
        $this->children[$childName] = $child;
        $child->setParent($this);
        $this->stat->cversion++;
        $this->stat->numChildren = count($this->children);
        return $this;
    }

    public function removeChild(string $childName): bool
    {
        if (isset($this->children[$childName])) {
            unset($this->children[$childName]);
            $this->stat->cversion++;
            $this->stat->numChildren = count($this->children);
            return true;
        }
        return false;
    }

    public function getChild(string $childName): ?ZNode
    {
        return $this->children[$childName] ?? null;
    }

    /**
     * @return array<string> Child node names
     */
    public function getChildrenNames(): array
    {
        return array_keys($this->children);
    }

    /**
     * @return array<string, ZNode>
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    public function hasChildren(): bool
    {
        return !empty($this->children);
    }

    public function getNextSequentialId(): string
    {
        $id = sprintf('%010d', $this->sequentialCounter);
        $this->sequentialCounter++;
        return $id;
    }
}
