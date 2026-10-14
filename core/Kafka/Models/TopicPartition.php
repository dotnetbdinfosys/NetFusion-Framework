<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\Kafka\Models;

use Stringable;

/**
 * Immutable identifier for a specific topic and partition.
 */
class TopicPartition implements Stringable
{
    public function __construct(
        public readonly string $topic,
        public readonly int $partition
    ) {
    }

    /**
     * Compare equality with another TopicPartition.
     */
    public function equals(self $other): bool
    {
        return $this->topic === $other->topic && $this->partition === $other->partition;
    }

    /**
     * Canonical string representation e.g. "orders-0".
     */
    public function toString(): string
    {
        return "{$this->topic}-{$this->partition}";
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function toArray(): array
    {
        return [
            'topic'     => $this->topic,
            'partition' => $this->partition,
        ];
    }

    public static function fromString(string $identifier): self
    {
        $lastHyphen = strrpos($identifier, '-');
        if ($lastHyphen === false) {
            return new self($identifier, 0);
        }

        $topic = substr($identifier, 0, $lastHyphen);
        $partition = (int) substr($identifier, $lastHyphen + 1);

        return new self($topic, $partition);
    }
}
