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

use NetFusion\Core\CEP\Contracts\EventInterface;

class NFATransition
{
    public const ACTION_TAKE = 'take';
    public const ACTION_IGNORE = 'ignore';
    public const ACTION_PROCEED = 'proceed';

    protected string $sourceState;
    protected string $targetState;
    protected string $action;
    protected ?PatternCondition $condition;

    public function __construct(
        string $sourceState,
        string $targetState,
        string $action = self::ACTION_TAKE,
        ?PatternCondition $condition = null
    ) {
        $this->sourceState = $sourceState;
        $this->targetState = $targetState;
        $this->action = $action;
        $this->condition = $condition;
    }

    public function getSourceState(): string
    {
        return $this->sourceState;
    }

    public function getTargetState(): string
    {
        return $this->targetState;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getCondition(): ?PatternCondition
    {
        return $this->condition;
    }

    /**
     * Check if transition condition is satisfied.
     *
     * @param EventInterface $event
     * @param array<string, array<EventInterface>> $context
     */
    public function matches(EventInterface $event, array $context = []): bool
    {
        if ($this->condition === null) {
            return true;
        }

        return $this->condition->evaluate($event, $context);
    }
}
