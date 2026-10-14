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
 * Distributed SQL execution plan describing shard routing and aggregation.
 */
class QueryPlan implements JsonSerializable
{
    /**
     * @param SqlNodeType $type
     * @param string $table
     * @param array<ShardDescriptor> $targetShards
     * @param bool $isScatterGather
     * @param string $pushdownSql
     * @param array<mixed> $pushdownParams
     * @param array<string, array{func: string, column: string, alias: string}> $aggregates
     * @param string|null $orderBy
     * @param string $orderDir
     * @param int|null $limit
     * @param int|null $offset
     * @param array<string, mixed> $insertData
     * @param array<string, mixed> $updateData
     * @param array<mixed> $where
     */
    public function __construct(
        public readonly SqlNodeType $type,
        public readonly string $table,
        public readonly array $targetShards,
        public readonly bool $isScatterGather,
        public readonly string $pushdownSql,
        public readonly array $pushdownParams = [],
        public readonly array $aggregates = [],
        public readonly ?string $orderBy = null,
        public readonly string $orderDir = 'ASC',
        public readonly ?int $limit = null,
        public readonly ?int $offset = null,
        public readonly array $insertData = [],
        public readonly array $updateData = [],
        public readonly array $where = []
    ) {
    }

    public function isPointLookup(): bool
    {
        return count($this->targetShards) === 1 && !$this->isScatterGather;
    }

    /**
     * @return array<string>
     */
    public function getTargetShardIds(): array
    {
        return array_map(fn(ShardDescriptor $s) => $s->shardId, $this->targetShards);
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'table' => $this->table,
            'target_shards' => $this->getTargetShardIds(),
            'is_point_lookup' => $this->isPointLookup(),
            'is_scatter_gather' => $this->isScatterGather,
            'pushdown_sql' => $this->pushdownSql,
            'aggregates' => $this->aggregates,
            'order_by' => $this->orderBy,
            'order_dir' => $this->orderDir,
            'limit' => $this->limit,
            'offset' => $this->offset,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
