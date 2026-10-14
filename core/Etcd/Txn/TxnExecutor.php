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

use NetFusion\Core\Etcd\Enums\EventType;
use NetFusion\Core\Etcd\Lease\LeaseManager;
use NetFusion\Core\Etcd\Models\TxnResponse;
use NetFusion\Core\Etcd\Models\WatchEvent;
use NetFusion\Core\Etcd\Storage\MvccStoreInterface;
use NetFusion\Core\Etcd\Watch\WatchHub;

/**
 * Atomic executor for etcd transactions (If-Then-Else engine).
 */
class TxnExecutor
{
    public function __construct(
        protected MvccStoreInterface $store,
        protected ?LeaseManager $leaseManager = null,
        protected ?WatchHub $watchHub = null
    ) {
    }

    /**
     * Atomically evaluate comparisons and execute the corresponding branch operations.
     */
    public function execute(TxnBuilder $builder): TxnResponse
    {
        // 1. Evaluate comparisons in If clause
        $comparesPassed = true;
        foreach ($builder->getCompares() as $compare) {
            $kv = $this->store->get($compare->key);
            if (!$compare->evaluate($kv)) {
                $comparesPassed = false;
                break;
            }
        }

        // 2. Select branch
        $opsToExecute = $comparesPassed ? $builder->getThenOps() : $builder->getElseOps();
        $responses = [];

        // 3. Execute branch operations
        foreach ($opsToExecute as $op) {
            $responses[] = $this->executeOp($op);
        }

        return new TxnResponse(
            succeeded: $comparesPassed,
            responses: $responses,
            revision: $this->store->getRevision()
        );
    }

    /**
     * Execute an individual TxnOp against the MVCC store and watchers.
     */
    protected function executeOp(TxnOp $op): mixed
    {
        return match ($op->type) {
            'put' => $this->executePut($op),
            'get' => $this->store->get($op->key),
            'delete' => $this->executeDelete($op),
            'range' => $this->store->range($op->key),
            'delete_range' => $this->executeDeleteRange($op),
            default => null,
        };
    }

    protected function executePut(TxnOp $op): mixed
    {
        $prevKv = null;
        $kv = $this->store->put($op->key, (string) $op->value, $op->leaseId, $prevKv);

        if ($op->leaseId > 0 && $this->leaseManager !== null) {
            $this->leaseManager->attachKey($op->leaseId, $op->key);
        }

        if ($this->watchHub !== null) {
            $this->watchHub->broadcast(new WatchEvent(
                type: EventType::PUT,
                kv: $kv,
                prevKv: $prevKv
            ));
        }

        return $kv;
    }

    protected function executeDelete(TxnOp $op): mixed
    {
        $prevKv = null;
        $tomb = $this->store->delete($op->key, $prevKv);

        if ($tomb !== null) {
            if ($prevKv?->leaseId && $this->leaseManager !== null) {
                $this->leaseManager->detachKey($prevKv->leaseId, $op->key);
            }

            if ($this->watchHub !== null) {
                $this->watchHub->broadcast(new WatchEvent(
                    type: EventType::DELETE,
                    kv: $tomb,
                    prevKv: $prevKv
                ));
            }
        }

        return $tomb;
    }

    protected function executeDeleteRange(TxnOp $op): int
    {
        $deletedKvs = [];
        $count = $this->store->deleteRange($op->key, $deletedKvs);

        if ($this->watchHub !== null && !empty($deletedKvs)) {
            foreach ($deletedKvs as $tomb) {
                $this->watchHub->broadcast(new WatchEvent(
                    type: EventType::DELETE,
                    kv: $tomb,
                    prevKv: null
                ));
            }
        }

        return $count;
    }
}
