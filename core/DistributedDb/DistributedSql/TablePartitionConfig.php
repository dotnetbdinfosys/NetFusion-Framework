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

/**
 * Sharding and partitioning configuration for a distributed database table.
 */
class TablePartitionConfig
{
    /**
     * @var array<string, ShardDescriptor>
     */
    private array $shards = [];

    /**
     * @param string $table Target table name
     * @param string $partitionKey Shard partition column name
     * @param PartitionStrategy $strategy Strategy type
     * @param array<ShardDescriptor> $shards Initial shard descriptors
     */
    public function __construct(
        public readonly string $table,
        public readonly string $partitionKey,
        public readonly PartitionStrategy $strategy = PartitionStrategy::HASH,
        array $shards = []
    ) {
        foreach ($shards as $shard) {
            $this->addShard($shard);
        }
    }

    public function addShard(ShardDescriptor $shard): self
    {
        $this->shards[$shard->shardId] = $shard;
        return $this;
    }

    /**
     * @return array<string, ShardDescriptor>
     */
    public function getShards(): array
    {
        return $this->shards;
    }

    public function getShard(string $shardId): ?ShardDescriptor
    {
        return $this->shards[$shardId] ?? null;
    }

    /**
     * Resolve target shards for a given partition key value.
     * If partition key is null, returns all shards (scatter-gather).
     *
     * @return array<ShardDescriptor>
     */
    public function getMatchingShards(mixed $partitionKeyValue = null): array
    {
        if ($partitionKeyValue === null) {
            return array_values($this->shards);
        }

        $matching = [];
        foreach ($this->shards as $shard) {
            if ($shard->matches($partitionKeyValue)) {
                $matching[] = $shard;
            }
        }

        return $matching;
    }
}
