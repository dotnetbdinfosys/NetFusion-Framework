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

namespace NetFusion\Core\CRDT;

use NetFusion\Core\CRDT\Types\CrdtInterface;
use NetFusion\Core\CRDT\Types\LwwRegister;
use NetFusion\Core\CRDT\Types\PnCounter;
use NetFusion\Core\CRDT\Types\OrSet;
use NetFusion\Core\CRDT\Types\RgaText;
use NetFusion\Core\CRDT\Types\MvRegister;
use NetFusion\Core\CRDT\Types\JsonCrdt;
use NetFusion\Core\CRDT\OT\OtServerEngine;
use NetFusion\Core\CRDT\Sync\SyncProtocol;
use NetFusion\Core\CRDT\Sync\SyncMessage;
use NetFusion\Core\CRDT\Enums\CrdtType;
use NetFusion\Core\CRDT\Exceptions\CrdtException;

/**
 * Master Central Facade for Enterprise Real-Time Collaborative CRDT & OT Synchronizer Fabric.
 */
class CrdtManager
{
    private string $nodeId;
    private array $config;
    /** @var array<string, CrdtInterface> */
    private array $instances = [];
    /** @var array<string, OtServerEngine> */
    private array $otEngines = [];
    private SyncProtocol $protocol;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'node_id'                 => 'node_' . bin2hex(random_bytes(4)),
            'default_type'            => 'rga_text',
            'compression_enabled'     => true,
            'auto_snapshot_threshold' => 100,
        ], $config);

        $this->nodeId   = (string)$this->config['node_id'];
        $this->protocol = new SyncProtocol($this->nodeId);
    }

    public function getNodeId(): string
    {
        return $this->nodeId;
    }

    public function getProtocol(): SyncProtocol
    {
        return $this->protocol;
    }

    /**
     * Get or create a CRDT document instance.
     */
    public function doc(string $name, CrdtType|string $type = CrdtType::RGA_TEXT): CrdtInterface
    {
        if (is_string($type)) {
            $type = CrdtType::from($type);
        }

        if (isset($this->instances[$name])) {
            return $this->instances[$name];
        }

        $crdt = match ($type) {
            CrdtType::LWW_REGISTER => new LwwRegister($this->nodeId),
            CrdtType::PN_COUNTER   => new PnCounter($this->nodeId),
            CrdtType::OR_SET       => new OrSet($this->nodeId),
            CrdtType::RGA_TEXT     => new RgaText($this->nodeId),
            CrdtType::MV_REGISTER  => new MvRegister($this->nodeId),
            CrdtType::JSON_CRDT    => new JsonCrdt($this->nodeId),
        };

        $this->instances[$name] = $crdt;
        return $crdt;
    }

    /**
     * Helper to get or create a PN-Counter instance.
     */
    public function counter(string $name): PnCounter
    {
        /** @var PnCounter $crdt */
        $crdt = $this->doc($name, CrdtType::PN_COUNTER);
        return $crdt;
    }

    /**
     * Helper to get or create an OR-Set instance.
     */
    public function set(string $name): OrSet
    {
        /** @var OrSet $crdt */
        $crdt = $this->doc($name, CrdtType::OR_SET);
        return $crdt;
    }

    /**
     * Helper to get or create an RGA Text document instance.
     */
    public function text(string $name): RgaText
    {
        /** @var RgaText $crdt */
        $crdt = $this->doc($name, CrdtType::RGA_TEXT);
        return $crdt;
    }

    /**
     * Helper to get or create a JSON CRDT document instance.
     */
    public function json(string $name): JsonCrdt
    {
        /** @var JsonCrdt $crdt */
        $crdt = $this->doc($name, CrdtType::JSON_CRDT);
        return $crdt;
    }

    /**
     * Get or create an OT server engine for centralized text transformation.
     */
    public function ot(string $documentId, string $initialContent = ''): OtServerEngine
    {
        if (!isset($this->otEngines[$documentId])) {
            $this->otEngines[$documentId] = new OtServerEngine($documentId, $initialContent);
        }
        return $this->otEngines[$documentId];
    }

    /**
     * Synchronize two replicas of a document.
     *
     * @return array{replicaA_value: mixed, replicaB_value: mixed}
     */
    public function syncReplicas(CrdtInterface $replicaA, CrdtInterface $replicaB): array
    {
        $stateA = $replicaA->getState();
        $stateB = $replicaB->getState();

        $replicaA->merge($stateB);
        $replicaB->merge($stateA);

        return [
            'replicaA_value' => $replicaA->getValue(),
            'replicaB_value' => $replicaB->getValue(),
        ];
    }

    /**
     * List all loaded instances.
     * @return array<string, CrdtInterface>
     */
    public function all(): array
    {
        return $this->instances;
    }

    public function reset(): void
    {
        $this->instances = [];
        $this->otEngines = [];
    }
}
