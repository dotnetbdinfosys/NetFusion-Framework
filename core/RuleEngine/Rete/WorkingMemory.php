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

namespace NetFusion\Core\RuleEngine\Rete;

use Countable;
use JsonSerializable;
use NetFusion\Core\RuleEngine\Contracts\FactInterface;
use NetFusion\Core\RuleEngine\Contracts\ReteNetworkInterface;
use NetFusion\Core\RuleEngine\Contracts\WorkingMemoryInterface;
use NetFusion\Core\RuleEngine\Fact\Fact;

class WorkingMemory implements WorkingMemoryInterface, Countable, JsonSerializable
{
    /** @var array<string, FactInterface> */
    protected array $facts = [];
    /** @var array<string, array<string, FactInterface>> Type-indexed facts */
    protected array $factsByType = [];
    protected ?ReteNetworkInterface $network = null;

    public function __construct(?ReteNetworkInterface $network = null)
    {
        $this->network = $network;
    }

    public function setNetwork(ReteNetworkInterface $network): self
    {
        $this->network = $network;
        return $this;
    }

    public function getNetwork(): ?ReteNetworkInterface
    {
        return $this->network;
    }

    public function assertFact(FactInterface $fact): FactInterface
    {
        $id = $fact->getId();
        $type = $fact->getType();

        // Check if identical fact exists
        if (isset($this->facts[$id])) {
            return $this->facts[$id];
        }

        $this->facts[$id] = $fact;
        if (!isset($this->factsByType[$type])) {
            $this->factsByType[$type] = [];
        }
        $this->factsByType[$type][$id] = $fact;

        // Propagate down RETE network if bound
        if ($this->network !== null) {
            $this->network->propagateAssert($fact);
        }

        return $fact;
    }

    public function retractFact(FactInterface|string $factOrId): bool
    {
        $id = is_string($factOrId) ? $factOrId : $factOrId->getId();

        if (!isset($this->facts[$id])) {
            return false;
        }

        $fact = $this->facts[$id];
        $type = $fact->getType();

        unset($this->facts[$id]);
        unset($this->factsByType[$type][$id]);

        // Propagate retraction down RETE network
        if ($this->network !== null) {
            $this->network->propagateRetract($fact);
        }

        return true;
    }

    public function modifyFact(FactInterface|string $factOrId, array $newAttributes): FactInterface
    {
        $id = is_string($factOrId) ? $factOrId : $factOrId->getId();

        if (!isset($this->facts[$id])) {
            $type = is_object($factOrId) ? $factOrId->getType() : 'Generic';
            $fact = new Fact($type, $newAttributes, $id);
            return $this->assertFact($fact);
        }

        $oldFact = $this->facts[$id];
        $updatedFact = $oldFact->with($newAttributes);

        $this->facts[$id] = $updatedFact;
        $this->factsByType[$updatedFact->getType()][$id] = $updatedFact;

        // Propagate modification down RETE network
        if ($this->network !== null) {
            $this->network->propagateModify($oldFact, $updatedFact);
        }

        return $updatedFact;
    }

    public function getFacts(?string $type = null): array
    {
        if ($type === null) {
            return array_values($this->facts);
        }

        return array_values($this->factsByType[$type] ?? []);
    }

    public function getFactById(string $id): ?FactInterface
    {
        return $this->facts[$id] ?? null;
    }

    public function clear(): void
    {
        $this->facts = [];
        $this->factsByType = [];

        if ($this->network !== null) {
            $this->network->reset();
        }
    }

    public function count(): int
    {
        return count($this->facts);
    }

    public function jsonSerialize(): array
    {
        return [
            'count' => count($this->facts),
            'types' => array_keys($this->factsByType),
            'facts' => array_values($this->facts),
        ];
    }
}
