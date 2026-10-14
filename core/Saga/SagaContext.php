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

namespace NetFusion\Core\Saga;

use ArrayAccess;
use JsonSerializable;

class SagaContext implements ArrayAccess, JsonSerializable
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        protected array $data = []
    ) {
    }

    public static function create(array $initialData = []): self
    {
        return new self($initialData);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): self
    {
        $this->data[$key] = $value;
        return $this;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function remove(string $key): self
    {
        unset($this->data[$key]);
        return $this;
    }

    public function merge(array $data): self
    {
        $this->data = array_merge($this->data, $data);
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->data;
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
        if ($offset === null) {
            $this->data[] = $value;
        } else {
            $this->set((string)$offset, $value);
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->remove((string)$offset);
    }

    public function jsonSerialize(): mixed
    {
        return $this->all();
    }
}
