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

namespace NetFusion\Core\CEP\Pattern;

use NetFusion\Core\CEP\Contracts\EventInterface;
use NetFusion\Core\CEP\Contracts\PatternInterface;

class Pattern implements PatternInterface
{
    public const CONTIGUITY_STRICT = 'strict';
    public const CONTIGUITY_RELAXED = 'relaxed';
    public const CONTIGUITY_NOT = 'not';

    protected string $name;
    /** @var array<string, array{name: string, contiguity: string, condition: PatternCondition, times: int, oneOrMore: bool}> */
    protected array $nodes = [];
    protected ?string $currentNode = null;
    protected ?float $windowSeconds = null;

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->currentNode = $name;
        $this->nodes[$name] = [
            'name' => $name,
            'contiguity' => self::CONTIGUITY_STRICT,
            'condition' => new PatternCondition(),
            'times' => 1,
            'oneOrMore' => false,
        ];
    }

    public static function begin(string $name): self
    {
        return new self($name);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function followedBy(string $name): self
    {
        $this->currentNode = $name;
        $this->nodes[$name] = [
            'name' => $name,
            'contiguity' => self::CONTIGUITY_STRICT,
            'condition' => new PatternCondition(),
            'times' => 1,
            'oneOrMore' => false,
        ];
        return $this;
    }

    public function followedByAny(string $name): self
    {
        $this->currentNode = $name;
        $this->nodes[$name] = [
            'name' => $name,
            'contiguity' => self::CONTIGUITY_RELAXED,
            'condition' => new PatternCondition(),
            'times' => 1,
            'oneOrMore' => false,
        ];
        return $this;
    }

    public function notFollowedBy(string $name): self
    {
        $this->currentNode = $name;
        $this->nodes[$name] = [
            'name' => $name,
            'contiguity' => self::CONTIGUITY_NOT,
            'condition' => new PatternCondition(),
            'times' => 1,
            'oneOrMore' => false,
        ];
        return $this;
    }

    public function where(callable $condition): self
    {
        if ($this->currentNode !== null && isset($this->nodes[$this->currentNode])) {
            $this->nodes[$this->currentNode]['condition']->where($condition);
        }
        return $this;
    }

    public function orWhere(callable $condition): self
    {
        if ($this->currentNode !== null && isset($this->nodes[$this->currentNode])) {
            $this->nodes[$this->currentNode]['condition']->orWhere($condition);
        }
        return $this;
    }

    public function whereType(string $eventType): self
    {
        return $this->where(fn(EventInterface $e) => $e->getType() === $eventType);
    }

    public function whereEquals(string $key, mixed $value): self
    {
        return $this->where(fn(EventInterface $e) => $e->get($key) === $value);
    }

    public function whereGreaterThan(string $key, float|int $value): self
    {
        return $this->where(fn(EventInterface $e) => (float) $e->get($key, 0) > $value);
    }

    public function whereLessThan(string $key, float|int $value): self
    {
        return $this->where(fn(EventInterface $e) => (float) $e->get($key, 0) < $value);
    }

    public function times(int $count): self
    {
        if ($this->currentNode !== null && isset($this->nodes[$this->currentNode])) {
            $this->nodes[$this->currentNode]['times'] = max(1, $count);
        }
        return $this;
    }

    public function oneOrMore(): self
    {
        if ($this->currentNode !== null && isset($this->nodes[$this->currentNode])) {
            $this->nodes[$this->currentNode]['oneOrMore'] = true;
        }
        return $this;
    }

    public function within(float $seconds): self
    {
        $this->windowSeconds = max(0.001, $seconds);
        return $this;
    }

    public function getWindowSeconds(): ?float
    {
        return $this->windowSeconds;
    }

    public function getNodes(): array
    {
        return $this->nodes;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'window_seconds' => $this->windowSeconds,
            'node_count' => count($this->nodes),
            'nodes' => array_keys($this->nodes),
        ];
    }
}
