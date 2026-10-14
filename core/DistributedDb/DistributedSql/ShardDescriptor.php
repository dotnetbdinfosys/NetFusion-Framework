<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | Author    : Md. Atiquzzaman Ashish
 | Company   : DotNetBD
 | Website   : www.dotnetbd.com
 | Copyright : © DotNetBD. All rights reserved.
 | License   : MIT
 | Version   : 1.0.0
 |
 |--------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace NetFusion\Core\DistributedDb\DistributedSql;

use JsonSerializable;

/**
 * Descriptor of a distributed table shard partition.
 */
class ShardDescriptor implements JsonSerializable
{
    /**
     * @param string $shardId Unique shard identifier (e.g. shard_0, shard_1)
     * @param string $nodeId Hosting node or database connection identifier
     * @param PartitionStrategy $strategy Partitioning strategy
     * @param int $hashModulus Modulus for HASH sharding
     * @param int $hashRemainder Expected remainder for HASH sharding
     * @param mixed $rangeStart Start of range inclusive (for RANGE)
     * @param mixed $rangeEnd End of range exclusive/inclusive (for RANGE)
     * @param array $allowedValues Specific discrete values (for LIST)
     */
    public function __construct(
        public readonly string $shardId,
        public readonly string $nodeId,
        public readonly PartitionStrategy $strategy,
        public readonly int $hashModulus = 1,
        public readonly int $hashRemainder = 0,
        public readonly mixed $rangeStart = null,
        public readonly mixed $rangeEnd = null,
        public readonly array $allowedValues = []
    ) {
    }

    /**
     * Test whether a partition key value routes to this shard.
     */
    public function matches(mixed $partitionKeyValue): bool
    {
        if ($this->strategy === PartitionStrategy::BROADCAST) {
            return true;
        }

        if ($this->strategy === PartitionStrategy::HASH) {
            $num = is_numeric($partitionKeyValue) ? (int) $partitionKeyValue : abs(crc32((string) $partitionKeyValue));
            return ($num % $this->hashModulus) === $this->hashRemainder;
        }

        if ($this->strategy === PartitionStrategy::RANGE) {
            if ($this->rangeStart !== null && $partitionKeyValue < $this->rangeStart) {
                return false;
            }
            if ($this->rangeEnd !== null && $partitionKeyValue >= $this->rangeEnd) {
                return false;
            }
            return true;
        }

        if ($this->strategy === PartitionStrategy::LIST) {
            return in_array($partitionKeyValue, $this->allowedValues, false);
        }

        return false;
    }

    public function toArray(): array
    {
        return [
            'shard_id' => $this->shardId,
            'node_id' => $this->nodeId,
            'strategy' => $this->strategy->value,
            'hash_modulus' => $this->hashModulus,
            'hash_remainder' => $this->hashRemainder,
            'range_start' => $this->rangeStart,
            'range_end' => $this->rangeEnd,
            'allowed_values' => $this->allowedValues,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            shardId: (string) ($data['shard_id'] ?? ''),
            nodeId: (string) ($data['node_id'] ?? ''),
            strategy: PartitionStrategy::from((string) ($data['strategy'] ?? 'HASH')),
            hashModulus: (int) ($data['hash_modulus'] ?? 1),
            hashRemainder: (int) ($data['hash_remainder'] ?? 0),
            rangeStart: $data['range_start'] ?? null,
            rangeEnd: $data['range_end'] ?? null,
            allowedValues: (array) ($data['allowed_values'] ?? [])
        );
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
