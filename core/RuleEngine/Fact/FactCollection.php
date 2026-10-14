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

namespace NetFusion\Core\RuleEngine\Fact;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use NetFusion\Core\RuleEngine\Contracts\FactInterface;
use Traversable;

/**
 * @implements IteratorAggregate<int, FactInterface>
 */
class FactCollection implements IteratorAggregate, Countable, JsonSerializable
{
    /** @var array<FactInterface> */
    protected array $facts = [];

    /**
     * @param array<FactInterface> $facts
     */
    public function __construct(array $facts = [])
    {
        $this->facts = array_values($facts);
    }

    public function add(FactInterface $fact): self
    {
        $this->facts[] = $fact;
        return $this;
    }

    /**
     * @return array<FactInterface>
     */
    public function all(): array
    {
        return $this->facts;
    }

    public function first(): ?FactInterface
    {
        return $this->facts[0] ?? null;
    }

    public function last(): ?FactInterface
    {
        $count = count($this->facts);
        return $count > 0 ? $this->facts[$count - 1] : null;
    }

    public function filter(callable $callback): self
    {
        return new self(array_filter($this->facts, $callback));
    }

    public function ofType(string $type): self
    {
        return $this->filter(fn(FactInterface $f) => $f->getType() === $type);
    }

    public function count(): int
    {
        return count($this->facts);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->facts);
    }

    public function jsonSerialize(): array
    {
        return array_map(fn(FactInterface $f) => $f->jsonSerialize(), $this->facts);
    }
}
