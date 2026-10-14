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

class PatternCondition
{
    /** @var array<callable> */
    protected array $predicates = [];
    /** @var array<callable> */
    protected array $orPredicates = [];

    public function __construct(?callable $initialPredicate = null)
    {
        if ($initialPredicate !== null) {
            $this->predicates[] = $initialPredicate;
        }
    }

    public static function of(callable $predicate): self
    {
        return new self($predicate);
    }

    public function where(callable $predicate): self
    {
        $this->predicates[] = $predicate;
        return $this;
    }

    public function orWhere(callable $predicate): self
    {
        $this->orPredicates[] = $predicate;
        return $this;
    }

    /**
     * Evaluate condition against incoming event and previously matched events in the pattern context.
     *
     * @param EventInterface $event
     * @param array<string, array<EventInterface>> $context
     */
    public function evaluate(EventInterface $event, array $context = []): bool
    {
        if (empty($this->predicates) && empty($this->orPredicates)) {
            return true;
        }

        // Check AND predicates
        $andPassed = true;
        foreach ($this->predicates as $predicate) {
            if (!call_user_func($predicate, $event, $context)) {
                $andPassed = false;
                break;
            }
        }

        if ($andPassed) {
            return true;
        }

        // If AND failed, check OR predicates
        foreach ($this->orPredicates as $orPredicate) {
            if (call_user_func($orPredicate, $event, $context)) {
                return true;
            }
        }

        return false;
    }
}
