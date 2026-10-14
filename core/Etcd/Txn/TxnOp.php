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

namespace NetFusion\Core\Etcd\Txn;

/**
 * Represents an operation executable inside an etcd transaction branch.
 */
class TxnOp
{
    public function __construct(
        public readonly string $type,
        public readonly string $key,
        public readonly ?string $value = null,
        public readonly int $leaseId = 0,
        public readonly bool $isPrefix = false
    ) {
    }

    /**
     * Create a PUT operation.
     */
    public static function put(string $key, string $value, int $leaseId = 0): self
    {
        return new self('put', $key, $value, $leaseId);
    }

    /**
     * Create a GET operation.
     */
    public static function get(string $key): self
    {
        return new self('get', $key);
    }

    /**
     * Create a DELETE operation.
     */
    public static function delete(string $key): self
    {
        return new self('delete', $key);
    }

    /**
     * Create a range query operation.
     */
    public static function range(string $prefix): self
    {
        return new self('range', $prefix, null, 0, true);
    }

    /**
     * Create a deleteRange operation.
     */
    public static function deleteRange(string $prefix): self
    {
        return new self('delete_range', $prefix, null, 0, true);
    }
}
