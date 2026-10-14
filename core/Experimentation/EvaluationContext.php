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

namespace NetFusion\Core\Experimentation;

class EvaluationContext
{
    /**
     * @param string|int|null      $userId
     * @param string|null          $anonymousId
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $groups
     */
    public function __construct(
        public readonly string|int|null $userId = null,
        public readonly ?string $anonymousId = null,
        public readonly array $attributes = [],
        public readonly array $groups = []
    ) {}

    /**
     * Create context from array or scalar ID.
     */
    public static function from(mixed $data): self
    {
        if ($data instanceof self) {
            return $data;
        }

        if (is_scalar($data)) {
            return new self(userId: $data);
        }

        if (is_array($data)) {
            return new self(
                userId: $data['user_id'] ?? $data['userId'] ?? $data['id'] ?? null,
                anonymousId: $data['anonymous_id'] ?? $data['anonymousId'] ?? null,
                attributes: (array)($data['attributes'] ?? $data),
                groups: (array)($data['groups'] ?? [])
            );
        }

        if (is_object($data)) {
            $id = method_exists($data, 'getKey') ? $data->getKey() : ($data->id ?? null);
            $attrs = method_exists($data, 'toArray') ? $data->toArray() : get_object_vars($data);
            return new self(userId: $id, attributes: $attrs);
        }

        return new self();
    }

    /**
     * Get a unique deterministic identifier for hashing (userId preferred, then anonymousId, fallback to random).
     */
    public function getIdentifier(): string
    {
        if ($this->userId !== null && $this->userId !== '') {
            return (string)$this->userId;
        }

        if ($this->anonymousId !== null && $this->anonymousId !== '') {
            return $this->anonymousId;
        }

        if (!empty($this->attributes['id'])) {
            return (string)$this->attributes['id'];
        }

        return 'guest_' . md5(serialize($this->attributes));
    }

    /**
     * Retrieve an attribute by dot-notated key.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if ($key === 'id' || $key === 'user_id' || $key === 'userId') {
            return $this->userId ?? $default;
        }

        if ($key === 'anonymous_id' || $key === 'anonymousId') {
            return $this->anonymousId ?? $default;
        }

        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }

        // Nested dot notation support
        $curr = $this->attributes;
        foreach (explode('.', $key) as $segment) {
            if (is_array($curr) && array_key_exists($segment, $curr)) {
                $curr = $curr[$segment];
            } else {
                return $default;
            }
        }

        return $curr;
    }

    /**
     * Check if an attribute exists.
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Export to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'anonymous_id' => $this->anonymousId,
            'attributes' => $this->attributes,
            'groups' => $this->groups,
            'identifier' => $this->getIdentifier(),
        ];
    }
}
