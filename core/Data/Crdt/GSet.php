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

class GSet implements CrdtInterface
{
    /** @var array<string, mixed> Elements indexed by serialized key */
    protected array $elements = [];

    public function __construct(array $initialElements = [])
    {
        foreach ($initialElements as $elem) {
            $this->add($elem);
        }
    }

    public function add(mixed $element): self
    {
        $key = is_scalar($element) ? (string)$element : serialize($element);
        $this->elements[$key] = $element;
        return $this;
    }

    public function contains(mixed $element): bool
    {
        $key = is_scalar($element) ? (string)$element : serialize($element);
        return isset($this->elements[$key]);
    }

    public function getValue(): array
    {
        return array_values($this->elements);
    }

    public function merge(CrdtInterface $other): static
    {
        if (!$other instanceof self) {
            throw new InvalidArgumentException('Cannot merge GSet with non-GSet CRDT.');
        }

        $otherState = $other->getState();
        foreach ($otherState['elements'] as $k => $v) {
            $this->elements[$k] = $v;
        }

        return $this;
    }

    public function getState(): array
    {
        return [
            'type' => 'g-set',
            'elements' => $this->elements,
        ];
    }

    public static function fromState(array $state): static
    {
        $instance = new static();
        $instance->elements = (array)($state['elements'] ?? []);
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
