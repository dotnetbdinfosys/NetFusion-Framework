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

namespace NetFusion\Core\Replication;

use NetFusion\Core\Replication\CRDTs\GCounter;
use NetFusion\Core\Replication\CRDTs\PNCounter;
use NetFusion\Core\Replication\CRDTs\ORSet;
use NetFusion\Core\Replication\CRDTs\LWWRegister;
use NetFusion\Core\Replication\CRDTs\MVRegister;

/**
 * Class ReplicationManager
 *
 * Central facade for the Multi-Region Data Replication Engine.
 *
 * Features:
 *   - Named CRDT registry (GCounter, PNCounter, ORSet, LWWRegister, MVRegister)
 *   - VectorClock management per named key
 *   - Region-aware merge protocol
 *   - Replication log (in-memory)
 */
class ReplicationManager
{
    private string $localRegion;

    /** @var array<string, GCounter> */
    private array $gCounters = [];

    /** @var array<string, PNCounter> */
    private array $pnCounters = [];

    /** @var array<string, ORSet> */
    private array $orSets = [];

    /** @var array<string, LWWRegister> */
    private array $lwwRegisters = [];

    /** @var array<string, MVRegister> */
    private array $mvRegisters = [];

    /** @var array<string, VectorClock> */
    private array $vectorClocks = [];

    /** @var array[] Replication operation log */
    private array $replicationLog = [];

    public function __construct(string $localRegion = 'region-1')
    {
        $this->localRegion = $localRegion;
    }

    // ------------------------------------------------------------------
    // Factory
    // ------------------------------------------------------------------

    public static function create(string $localRegion = 'region-1'): self
    {
        return new self($localRegion);
    }

    public function getLocalRegion(): string { return $this->localRegion; }

    // ------------------------------------------------------------------
    // GCounter
    // ------------------------------------------------------------------

    public function gCounter(string $name): GCounter
    {
        return $this->gCounters[$name] ??= new GCounter();
    }

    public function incrementGCounter(string $name, int $by = 1): int
    {
        $this->gCounters[$name] = $this->gCounter($name)->increment($this->localRegion, $by);
        $this->log('gcounter.increment', $name, ['by' => $by]);
        return $this->gCounters[$name]->value();
    }

    // ------------------------------------------------------------------
    // PNCounter
    // ------------------------------------------------------------------

    public function pnCounter(string $name): PNCounter
    {
        return $this->pnCounters[$name] ??= new PNCounter();
    }

    public function incrementPNCounter(string $name, int $by = 1): int
    {
        $this->pnCounters[$name] = $this->pnCounter($name)->increment($this->localRegion, $by);
        $this->log('pncounter.increment', $name, ['by' => $by]);
        return $this->pnCounters[$name]->value();
    }

    public function decrementPNCounter(string $name, int $by = 1): int
    {
        $this->pnCounters[$name] = $this->pnCounter($name)->decrement($this->localRegion, $by);
        $this->log('pncounter.decrement', $name, ['by' => $by]);
        return $this->pnCounters[$name]->value();
    }

    // ------------------------------------------------------------------
    // ORSet
    // ------------------------------------------------------------------

    public function orSet(string $name): ORSet
    {
        return $this->orSets[$name] ??= new ORSet();
    }

    public function addToSet(string $name, mixed $element): void
    {
        $this->orSets[$name] = $this->orSet($name)->add($element);
        $this->log('orset.add', $name, ['element' => $element]);
    }

    public function removeFromSet(string $name, mixed $element): void
    {
        $this->orSets[$name] = $this->orSet($name)->remove($element);
        $this->log('orset.remove', $name, ['element' => $element]);
    }

    // ------------------------------------------------------------------
    // LWWRegister
    // ------------------------------------------------------------------

    public function lwwRegister(string $name): LWWRegister
    {
        return $this->lwwRegisters[$name] ??= new LWWRegister();
    }

    public function setLww(string $name, mixed $value): void
    {
        $this->lwwRegisters[$name] = $this->lwwRegister($name)->set($value, $this->localRegion);
        $this->log('lww.set', $name, ['value' => $value]);
    }

    // ------------------------------------------------------------------
    // MVRegister
    // ------------------------------------------------------------------

    public function mvRegister(string $name): MVRegister
    {
        return $this->mvRegisters[$name] ??= new MVRegister();
    }

    public function setMv(string $name, mixed $value): void
    {
        $this->mvRegisters[$name] = $this->mvRegister($name)->set($value, $this->localRegion);
        $this->log('mv.set', $name, ['value' => $value]);
    }

    // ------------------------------------------------------------------
    // VectorClock
    // ------------------------------------------------------------------

    public function vectorClock(string $name): VectorClock
    {
        return $this->vectorClocks[$name] ??= new VectorClock();
    }

    public function incrementClock(string $name): VectorClock
    {
        $this->vectorClocks[$name] = $this->vectorClock($name)->increment($this->localRegion);
        return $this->vectorClocks[$name];
    }

    // ------------------------------------------------------------------
    // Log
    // ------------------------------------------------------------------

    private function log(string $op, string $name, array $data = []): void
    {
        $this->replicationLog[] = [
            'operation'  => $op,
            'key'        => $name,
            'region'     => $this->localRegion,
            'data'       => $data,
            'timestamp'  => date('Y-m-d H:i:s'),
        ];
    }

    public function getReplicationLog(): array { return $this->replicationLog; }

    public function getStats(): array
    {
        return [
            'region'       => $this->localRegion,
            'g_counters'   => count($this->gCounters),
            'pn_counters'  => count($this->pnCounters),
            'or_sets'      => count($this->orSets),
            'lww_registers'=> count($this->lwwRegisters),
            'mv_registers' => count($this->mvRegisters),
            'log_entries'  => count($this->replicationLog),
        ];
    }
}
