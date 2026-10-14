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

namespace NetFusion\Core\CEP;

use JsonSerializable;
use NetFusion\Core\CEP\Contracts\EventInterface;

class Event implements EventInterface, JsonSerializable
{
    protected string $id;
    protected string $type;
    protected float $timestamp;
    protected array $payload;
    protected array $headers;

    /**
     * @param string $type
     * @param array<string, mixed> $payload
     * @param float|null $timestamp
     * @param string|null $id
     * @param array<string, mixed> $headers
     */
    public function __construct(
        string $type,
        array $payload = [],
        ?float $timestamp = null,
        ?string $id = null,
        array $headers = []
    ) {
        $this->type = $type;
        $this->payload = $payload;
        $this->timestamp = $timestamp ?? microtime(true);
        $this->id = $id ?? self::generateUuid();
        $this->headers = $headers;
    }

    public static function create(
        string $type,
        array $payload = [],
        ?float $timestamp = null,
        ?string $id = null,
        array $headers = []
    ): self {
        return new self($type, $payload, $timestamp, $id, $headers);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['type'] ?? 'generic'),
            (array) ($data['payload'] ?? []),
            isset($data['timestamp']) ? (float) $data['timestamp'] : null,
            isset($data['id']) ? (string) $data['id'] : null,
            (array) ($data['headers'] ?? [])
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getTimestamp(): float
    {
        return $this->timestamp;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->payload)) {
            return $this->payload[$key];
        }

        // Support dot notation
        $segments = explode('.', $key);
        $current = $this->payload;

        foreach ($segments as $segment) {
            if (is_array($current) && array_key_exists($segment, $current)) {
                $current = $current[$segment];
            } else {
                return $default;
            }
        }

        return $current;
    }

    public function has(string $key): bool
    {
        if (array_key_exists($key, $this->payload)) {
            return true;
        }

        $segments = explode('.', $key);
        $current = $this->payload;

        foreach ($segments as $segment) {
            if (is_array($current) && array_key_exists($segment, $current)) {
                $current = $current[$segment];
            } else {
                return false;
            }
        }

        return true;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name, mixed $default = null): mixed
    {
        return $this->headers[$name] ?? $default;
    }

    public function withPayload(array $payload): self
    {
        $clone = clone $this;
        $clone->payload = array_merge($clone->payload, $payload);
        return $clone;
    }

    public function withHeader(string $name, mixed $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'timestamp' => $this->timestamp,
            'payload' => $this->payload,
            'headers' => $this->headers,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    protected static function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
