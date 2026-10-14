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

namespace NetFusion\Core\CEP\Pattern;

class NFAState
{
    public const TYPE_START = 'start';
    public const TYPE_INTERMEDIATE = 'intermediate';
    public const TYPE_FINAL = 'final';
    public const TYPE_STOP = 'stop';

    protected string $name;
    protected string $type;
    /** @var array<NFATransition> */
    protected array $transitions = [];

    public function __construct(string $name, string $type = self::TYPE_INTERMEDIATE)
    {
        $this->name = $name;
        $this->type = $type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function isStart(): bool
    {
        return $this->type === self::TYPE_START;
    }

    public function isFinal(): bool
    {
        return $this->type === self::TYPE_FINAL;
    }

    public function addTransition(NFATransition $transition): self
    {
        $this->transitions[] = $transition;
        return $this;
    }

    /**
     * @return array<NFATransition>
     */
    public function getTransitions(): array
    {
        return $this->transitions;
    }
}
