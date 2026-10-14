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

use NetFusion\Core\Etcd\Enums\CompareResult;
use NetFusion\Core\Etcd\Enums\CompareTarget;
use NetFusion\Core\Etcd\Models\KeyRevision;

/**
 * Represents a single comparison predicate in an etcd transaction 'If' clause.
 */
class CompareOp
{
    public function __construct(
        public readonly string $key,
        public readonly CompareTarget $target,
        public readonly CompareResult $result,
        public readonly mixed $value
    ) {
    }

    /**
     * Evaluate this comparison predicate against a key revision.
     */
    public function evaluate(?KeyRevision $kv): bool
    {
        $actual = $this->extractActual($kv);
        $expected = $this->value;

        return match ($this->result) {
            CompareResult::EQUAL     => $actual === $expected || (is_numeric($actual) && is_numeric($expected) && (int) $actual === (int) $expected),
            CompareResult::NOT_EQUAL => $actual !== $expected && (!is_numeric($actual) || !is_numeric($expected) || (int) $actual !== (int) $expected),
            CompareResult::GREATER   => is_numeric($actual) && is_numeric($expected) ? ((int) $actual > (int) $expected) : ((string) $actual > (string) $expected),
            CompareResult::LESS      => is_numeric($actual) && is_numeric($expected) ? ((int) $actual < (int) $expected) : ((string) $actual < (string) $expected),
        };
    }

    /**
     * Extract the target comparison value from the revision.
     */
    protected function extractActual(?KeyRevision $kv): mixed
    {
        if ($kv === null || $kv->isTombstone) {
            return match ($this->target) {
                CompareTarget::VALUE   => null,
                CompareTarget::VERSION => 0,
                CompareTarget::CREATE  => 0,
                CompareTarget::MOD     => 0,
            };
        }

        return match ($this->target) {
            CompareTarget::VALUE   => (string) $kv->value,
            CompareTarget::VERSION => $kv->version,
            CompareTarget::CREATE  => $kv->createRevision,
            CompareTarget::MOD     => $kv->modRevision,
        };
    }

    /**
     * Static constructors for convenience.
     */
    public static function value(string $key, CompareResult $op, string $value): self
    {
        return new self($key, CompareTarget::VALUE, $op, $value);
    }

    public static function version(string $key, CompareResult $op, int $version): self
    {
        return new self($key, CompareTarget::VERSION, $op, $version);
    }

    public static function createRevision(string $key, CompareResult $op, int $revision): self
    {
        return new self($key, CompareTarget::CREATE, $op, $revision);
    }

    public static function modRevision(string $key, CompareResult $op, int $revision): self
    {
        return new self($key, CompareTarget::MOD, $op, $revision);
    }
}
