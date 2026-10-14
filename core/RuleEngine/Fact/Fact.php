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

use ArrayAccess;
use NetFusion\Core\RuleEngine\Contracts\FactInterface;

/**
 * @implements ArrayAccess<string, mixed>
 */
class Fact implements FactInterface, ArrayAccess
{
    protected string $id;
    protected string $type;
    /** @var array<string, mixed> */
    protected array $attributes;
    protected float $timestamp;

    /**
     * @param string $type Fact category / entity type (e.g. 'Order', 'Customer', 'Metric')
     * @param array<string, mixed> $attributes Key-value payload
     * @param string|null $id Optional unique identifier
     */
    public function __construct(string $type, array $attributes = [], ?string $id = null)
    {
        $this->type = trim($type);
        $this->attributes = $attributes;
        $this->id = $id ?? 'fact_' . bin2hex(random_bytes(8));
        $this->timestamp = microtime(true);
    }

    public static function of(string $type, array $attributes = [], ?string $id = null): self
    {
        return new self($type, $attributes, $id);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }

        // Support nested dot notation
        if (str_contains($key, '.')) {
            $current = $this->attributes;
            foreach (explode('.', $key) as $segment) {
                if (is_array($current) && array_key_exists($segment, $current)) {
                    $current = $current[$segment];
                } else {
                    return $default;
                }
            }
            return $current;
        }

        return $default;
    }

    public function has(string $key): bool
    {
        if (array_key_exists($key, $this->attributes)) {
            return true;
        }
        if (str_contains($key, '.')) {
            return $this->get($key) !== null;
        }
        return false;
    }

    public function getHash(): string
    {
        return md5($this->type . ':' . json_encode($this->attributes));
    }

    public function with(array $attributes): self
    {
        $merged = array_merge($this->attributes, $attributes);
        return new self($this->type, $merged, $this->id);
    }

    public function getTimestamp(): float
    {
        return $this->timestamp;
    }

    public function __get(string $name): mixed
    {
        return $this->get($name);
    }

    public function __isset(string $name): bool
    {
        return $this->has($name);
    }

    // ArrayAccess implementation
    public function offsetExists(mixed $offset): bool
    {
        return $this->has((string)$offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get((string)$offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->attributes[(string)$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->attributes[(string)$offset]);
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'attributes' => $this->attributes,
            'timestamp' => $this->timestamp,
            'hash' => $this->getHash(),
        ];
    }
}
