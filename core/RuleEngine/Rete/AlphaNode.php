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

use NetFusion\Core\RuleEngine\Contracts\FactInterface;

class AlphaNode
{
    /** @var callable */
    protected $predicate;
    /** @var array<string, FactInterface> Alpha memory matching this condition */
    protected array $memory = [];
    /** @var array<AlphaNode|BetaNode|TerminalNode> */
    protected array $children = [];
    /** @var string|null Node identifier for graph inspection */
    protected ?string $id = null;

    public function __construct(callable $predicate, ?string $id = null)
    {
        $this->predicate = $predicate;
        $this->id = $id ?? 'alpha_' . bin2hex(random_bytes(4));
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function addChild(mixed $child): self
    {
        $this->children[] = $child;
        return $this;
    }

    /**
     * @return array<mixed>
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * @return array<string, FactInterface>
     */
    public function getMemory(): array
    {
        return $this->memory;
    }

    public function assertFact(FactInterface $fact): void
    {
        $predicate = $this->predicate;
        $matches = false;

        try {
            $matches = (bool) $predicate($fact);
        } catch (\Throwable) {
            $matches = false;
        }

        if (!$matches) {
            return;
        }

        $this->memory[$fact->getId()] = $fact;

        foreach ($this->children as $child) {
            if ($child instanceof AlphaNode) {
                $child->assertFact($fact);
            } elseif ($child instanceof BetaNode) {
                // If child is BetaNode, determine if we feed left or right input
                if ($child->getRightAlphaSource() === $this) {
                    $child->assertRightFact($fact);
                } else {
                    $child->assertLeftTuple([$fact]);
                }
            } elseif ($child instanceof TerminalNode) {
                $child->assertTuple([$fact]);
            }
        }
    }

    public function retractFact(FactInterface $fact): void
    {
        $id = $fact->getId();
        if (!isset($this->memory[$id])) {
            return;
        }

        unset($this->memory[$id]);

        foreach ($this->children as $child) {
            if ($child instanceof AlphaNode) {
                $child->retractFact($fact);
            } elseif ($child instanceof BetaNode) {
                if ($child->getRightAlphaSource() === $this) {
                    $child->retractRightFact($fact);
                } else {
                    $child->retractLeftTuple([$fact]);
                }
            } elseif ($child instanceof TerminalNode) {
                $child->retractTuple([$fact]);
            }
        }
    }

    public function modifyFact(FactInterface $oldFact, FactInterface $newFact): void
    {
        $this->retractFact($oldFact);
        $this->assertFact($newFact);
    }

    public function clear(): void
    {
        $this->memory = [];
        foreach ($this->children as $child) {
            if (method_exists($child, 'clear')) {
                $child->clear();
            }
        }
    }
}
