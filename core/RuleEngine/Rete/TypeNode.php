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

class TypeNode
{
    protected string $type;
    /** @var array<string, FactInterface> Alpha memory for this fact type */
    protected array $memory = [];
    /** @var array<AlphaNode|BetaNode|TerminalNode> */
    protected array $children = [];

    public function __construct(string $type)
    {
        $this->type = trim($type);
    }

    public function getType(): string
    {
        return $this->type;
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
        if ($fact->getType() !== $this->type) {
            return;
        }

        $this->memory[$fact->getId()] = $fact;

        foreach ($this->children as $child) {
            if ($child instanceof AlphaNode) {
                $child->assertFact($fact);
            } elseif ($child instanceof BetaNode) {
                $child->assertRightFact($fact);
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
                $child->retractRightFact($fact);
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
