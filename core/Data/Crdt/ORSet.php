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

namespace NetFusion\Core\Data\Crdt;

use InvalidArgumentException;
use NetFusion\Core\Data\Crdt\Contracts\CrdtInterface;

class ORSet implements CrdtInterface
{
    /** @var array<string, array{element: mixed, tag: string}> Tag => Item */
    protected array $adds = [];

    /** @var array<string, bool> Tag => true */
    protected array $removes = [];

    public function __construct(array $initialElements = [])
    {
        foreach ($initialElements as $elem) {
            $this->add($elem);
        }
    }

    public function add(mixed $element): string
    {
        $tag = bin2hex(random_bytes(16));
        $this->adds[$tag] = [
            'element' => $element,
            'tag' => $tag,
        ];
        return $tag;
    }

    public function remove(mixed $element): int
    {
        $targetKey = is_scalar($element) ? (string)$element : serialize($element);
        $removedCount = 0;

        foreach ($this->adds as $tag => $item) {
            $elemKey = is_scalar($item['element']) ? (string)$item['element'] : serialize($item['element']);
            if ($elemKey === $targetKey && !isset($this->removes[$tag])) {
                $this->removes[$tag] = true;
                $removedCount++;
            }
        }

        return $removedCount;
    }

    public function contains(mixed $element): bool
    {
        $targetKey = is_scalar($element) ? (string)$element : serialize($element);

        foreach ($this->adds as $tag => $item) {
            if (isset($this->removes[$tag])) {
                continue;
            }
            $elemKey = is_scalar($item['element']) ? (string)$item['element'] : serialize($item['element']);
            if ($elemKey === $targetKey) {
                return true;
            }
        }

        return false;
    }

    public function getValue(): array
    {
        $active = [];
        $seen = [];

        foreach ($this->adds as $tag => $item) {
            if (isset($this->removes[$tag])) {
                continue;
            }
            $key = is_scalar($item['element']) ? (string)$item['element'] : serialize($item['element']);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $active[] = $item['element'];
            }
        }

        return $active;
    }

    public function merge(CrdtInterface $other): static
    {
        if (!$other instanceof self) {
            throw new InvalidArgumentException('Cannot merge ORSet with non-ORSet CRDT.');
        }

        $otherState = $other->getState();

        foreach ($otherState['adds'] as $tag => $item) {
            if (!isset($this->adds[$tag])) {
                $this->adds[$tag] = $item;
            }
        }

        foreach ($otherState['removes'] as $tag => $val) {
            $this->removes[$tag] = true;
        }

        return $this;
    }

    public function getState(): array
    {
        return [
            'type' => 'or-set',
            'adds' => $this->adds,
            'removes' => $this->removes,
        ];
    }

    public static function fromState(array $state): static
    {
        $instance = new static();
        $instance->adds = (array)($state['adds'] ?? []);
        $instance->removes = (array)($state['removes'] ?? []);
        return $instance;
    }

    public function jsonSerialize(): array
    {
        return [
            'value' => $this->getValue(),
            'state' => $this->getState(),
        ];
    }
}
