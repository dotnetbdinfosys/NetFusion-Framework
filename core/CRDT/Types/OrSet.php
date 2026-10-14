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

namespace NetFusion\Core\CRDT\Types;

use NetFusion\Core\CRDT\Enums\CrdtType;

/**
 * Observed-Remove Set (ORSet) CRDT.
 * Elements can be added and removed concurrently without losing concurrent additions.
 * Each addition generates a unique UUID/tag. Deleting an element removes all observed tags.
 */
class OrSet implements CrdtInterface
{
    private string $nodeId;
    /** @var array<string, array<string, mixed>> element => [tag => tagData] */
    private array $addSet = [];
    /** @var array<string, bool> tag => true */
    private array $removeSet = [];

    public function __construct(string $nodeId, array $addSet = [], array $removeSet = [])
    {
        $this->nodeId    = $nodeId;
        $this->addSet    = $addSet;
        $this->removeSet = $removeSet;
    }

    public function getType(): CrdtType
    {
        return CrdtType::OR_SET;
    }

    public function getNodeId(): string
    {
        return $this->nodeId;
    }

    public function add(mixed $element): string
    {
        $serializedKey = is_scalar($element) ? (string)$element : json_encode($element);
        $tag = bin2hex(random_bytes(8)) . '_' . $this->nodeId;

        if (!isset($this->addSet[$serializedKey])) {
            $this->addSet[$serializedKey] = [];
        }

        $this->addSet[$serializedKey][$tag] = [
            'element' => $element,
            'tag'     => $tag,
            'time'    => microtime(true),
        ];

        return $tag;
    }

    public function remove(mixed $element): bool
    {
        $serializedKey = is_scalar($element) ? (string)$element : json_encode($element);
        if (!isset($this->addSet[$serializedKey])) {
            return false;
        }

        // Tombstone all currently observed tags for this element
        foreach (array_keys($this->addSet[$serializedKey]) as $tag) {
            $this->removeSet[$tag] = true;
        }

        return true;
    }

    public function contains(mixed $element): bool
    {
        $serializedKey = is_scalar($element) ? (string)$element : json_encode($element);
        if (!isset($this->addSet[$serializedKey])) {
            return false;
        }

        foreach (array_keys($this->addSet[$serializedKey]) as $tag) {
            if (!isset($this->removeSet[$tag])) {
                return true;
            }
        }

        return false;
    }

    public function getValue(): array
    {
        $elements = [];
        foreach ($this->addSet as $serializedKey => $tags) {
            $alive = false;
            $sampleElement = null;

            foreach ($tags as $tag => $data) {
                if (!isset($this->removeSet[$tag])) {
                    $alive = true;
                    $sampleElement = $data['element'];
                    break;
                }
            }

            if ($alive) {
                $elements[] = $sampleElement;
            }
        }

        return $elements;
    }

    public function getState(): array
    {
        return [
            'type'       => $this->getType()->value,
            'node_id'    => $this->nodeId,
            'add_set'    => $this->addSet,
            'remove_set' => $this->removeSet,
        ];
    }

    public function merge(array $remoteState): self
    {
        $remoteAddSet    = $remoteState['add_set'] ?? [];
        $remoteRemoveSet = $remoteState['remove_set'] ?? [];

        // Union add sets
        foreach ($remoteAddSet as $key => $tags) {
            if (!isset($this->addSet[$key])) {
                $this->addSet[$key] = [];
            }
            foreach ($tags as $tag => $data) {
                $this->addSet[$key][$tag] = $data;
            }
        }

        // Union remove sets
        foreach ($remoteRemoveSet as $tag => $bool) {
            $this->removeSet[$tag] = true;
        }

        return $this;
    }

    public function delta(?array $sinceVectorClock = null): array
    {
        return $this->getState();
    }
}
