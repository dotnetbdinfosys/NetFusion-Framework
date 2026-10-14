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

namespace NetFusion\Core\Telemetry\Context;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use ArrayIterator;
use JsonSerializable;
use Traversable;

/**
 * W3C Baggage implementation for distributed context propagation.
 * Format: key1=value1,key2=value2;property1=foo
 */
class Baggage implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    /** @var array<string, array{value: string, metadata: array<string, string>}> */
    protected array $entries = [];

    /**
     * @param array<string, string|array{value: string, metadata?: array<string, string>}> $initial
     */
    public function __construct(array $initial = [])
    {
        foreach ($initial as $key => $val) {
            if (is_array($val)) {
                $this->set((string)$key, (string)($val['value'] ?? ''), (array)($val['metadata'] ?? []));
            } else {
                $this->set((string)$key, (string)$val);
            }
        }
    }

    public static function create(array $initial = []): self
    {
        return new self($initial);
    }

    /**
     * Parse W3C Baggage header string.
     * Example: "userId=alice,serverNode=dfw-01;cost=10,sessionId=xyz"
     */
    public static function fromHeader(string $header): self
    {
        $baggage = new self();
        if (trim($header) === '') {
            return $baggage;
        }

        $pairs = explode(',', $header);
        foreach ($pairs as $pair) {
            $pair = trim($pair);
            if ($pair === '') {
                continue;
            }

            // Check for metadata properties separated by ';'
            $metaParts = explode(';', $pair);
            $mainItem = array_shift($metaParts);

            if (!str_contains($mainItem, '=')) {
                continue;
            }

            [$key, $val] = explode('=', $mainItem, 2);
            $key = trim(urldecode($key));
            $val = trim(urldecode($val));

            $metadata = [];
            foreach ($metaParts as $prop) {
                if (str_contains($prop, '=')) {
                    [$pKey, $pVal] = explode('=', $prop, 2);
                    $metadata[trim(urldecode($pKey))] = trim(urldecode($pVal));
                } else {
                    $metadata[trim(urldecode($prop))] = '';
                }
            }

            $baggage->set($key, $val, $metadata);
        }

        return $baggage;
    }

    public function set(string $key, string $value, array $metadata = []): self
    {
        $this->entries[$key] = [
            'value' => $value,
            'metadata' => $metadata,
        ];
        return $this;
    }

    public function get(string $key, ?string $default = null): ?string
    {
        return $this->entries[$key]['value'] ?? $default;
    }

    public function getMetadata(string $key): array
    {
        return $this->entries[$key]['metadata'] ?? [];
    }

    public function has(string $key): bool
    {
        return isset($this->entries[$key]);
    }

    public function remove(string $key): self
    {
        unset($this->entries[$key]);
        return $this;
    }

    /**
     * Serialize to W3C Baggage header string.
     */
    public function toHeader(): string
    {
        $items = [];
        foreach ($this->entries as $key => $data) {
            $item = urlencode($key) . '=' . urlencode($data['value']);
            foreach ($data['metadata'] as $mKey => $mVal) {
                $item .= ';' . urlencode($mKey);
                if ($mVal !== '') {
                    $item .= '=' . urlencode($mVal);
                }
            }
            $items[] = $item;
        }

        return implode(',', $items);
    }

    /**
     * @return array<string, string>
     */
    public function getAll(): array
    {
        $result = [];
        foreach ($this->entries as $k => $v) {
            $result[$k] = $v['value'];
        }
        return $result;
    }

    public function count(): int
    {
        return count($this->entries);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->getAll());
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->has((string)$offset);
    }

    public function offsetGet(mixed $offset): ?string
    {
        return $this->get((string)$offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->set((string)$offset, (string)$value);
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->remove((string)$offset);
    }

    public function jsonSerialize(): mixed
    {
        return $this->getAll();
    }

    public function __toString(): string
    {
        return $this->toHeader();
    }
}
