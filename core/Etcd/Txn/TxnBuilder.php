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

use NetFusion\Core\Etcd\Models\TxnResponse;

/**
 * Fluent builder for creating etcd If-Then-Else atomic transactions.
 */
class TxnBuilder
{
    /** @var array<CompareOp> */
    protected array $compares = [];

    /** @var array<TxnOp> */
    protected array $thenOps = [];

    /** @var array<TxnOp> */
    protected array $elseOps = [];

    protected ?TxnExecutor $executor;

    public function __construct(?TxnExecutor $executor = null)
    {
        $this->executor = $executor;
    }

    /**
     * Add comparison preconditions (If clause).
     */
    public function If(CompareOp ...$compares): self
    {
        foreach ($compares as $compare) {
            $this->compares[] = $compare;
        }
        return $this;
    }

    /**
     * Alias for If clause.
     */
    public function when(CompareOp ...$compares): self
    {
        return $this->If(...$compares);
    }

    /**
     * Add operations executed if all preconditions evaluate to true.
     */
    public function Then(TxnOp ...$ops): self
    {
        foreach ($ops as $op) {
            $this->thenOps[] = $op;
        }
        return $this;
    }

    /**
     * Add operations executed if any precondition evaluates to false.
     */
    public function Else(TxnOp ...$ops): self
    {
        foreach ($ops as $op) {
            $this->elseOps[] = $op;
        }
        return $this;
    }

    /**
     * Get all comparison operations.
     *
     * @return array<CompareOp>
     */
    public function getCompares(): array
    {
        return $this->compares;
    }

    /**
     * Get operations in the Then branch.
     *
     * @return array<TxnOp>
     */
    public function getThenOps(): array
    {
        return $this->thenOps;
    }

    /**
     * Get operations in the Else branch.
     *
     * @return array<TxnOp>
     */
    public function getElseOps(): array
    {
        return $this->elseOps;
    }

    /**
     * Execute and commit the transaction.
     */
    public function commit(): TxnResponse
    {
        if ($this->executor === null) {
            throw new \RuntimeException('etcd: TxnExecutor is required to commit a transaction');
        }

        return $this->executor->execute($this);
    }
}
