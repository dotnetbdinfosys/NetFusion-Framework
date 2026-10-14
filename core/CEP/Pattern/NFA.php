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

class NFA
{
    protected Pattern $pattern;
    /** @var array<string, NFAState> */
    protected array $states = [];
    protected string $startState = '__start__';
    protected string $finalState = '__final__';

    /**
     * Active computation paths per partition key.
     * Structure:
     * [partitionKey => [
     *    [
     *      'state' => string,
     *      'events' => array<string, array<EventInterface>>,
     *      'startTimestamp' => float,
     *      'count' => int
     *    ]
     * ]]
     * @var array<string, array<array{state: string, events: array<string, array<EventInterface>>, startTimestamp: float, count: int}>>
     */
    protected array $computationPaths = [];

    public function __construct(Pattern $pattern)
    {
        $this->pattern = $pattern;
        $this->compile();
    }

    public static function compileFrom(Pattern $pattern): self
    {
        return new self($pattern);
    }

    /**
     * Compile declarative Pattern into NFA graph.
     */
    protected function compile(): void
    {
        $nodes = array_values($this->pattern->getNodes());
        $nodeCount = count($nodes);

        $this->states[$this->startState] = new NFAState($this->startState, NFAState::TYPE_START);
        $this->states[$this->finalState] = new NFAState($this->finalState, NFAState::TYPE_FINAL);

        for ($i = 0; $i < $nodeCount; $i++) {
            $node = $nodes[$i];
            $nodeName = $node['name'];
            $this->states[$nodeName] = new NFAState($nodeName, NFAState::TYPE_INTERMEDIATE);
        }

        // Connect Intermediate Nodes
        for ($i = 0; $i < $nodeCount - 1; $i++) {
            $node = $nodes[$i];
            $nodeName = $node['name'];
            $nextNode = $nodes[$i + 1];
            $nextTarget = $nextNode['name'];
            $nextCondition = $nextNode['condition'];

            // If relaxed contiguity, add IGNORE self-loop
            if ($nextNode['contiguity'] === Pattern::CONTIGUITY_RELAXED) {
                $this->states[$nodeName]->addTransition(
                    new NFATransition($nodeName, $nodeName, NFATransition::ACTION_IGNORE)
                );
            }

            // Next node transition
            $this->states[$nodeName]->addTransition(
                new NFATransition($nodeName, $nextTarget, NFATransition::ACTION_TAKE, $nextCondition)
            );
        }
    }

    /**
     * Process an incoming event for a partition key through the NFA.
     *
     * @return array<MatchedPattern> Emitted matched patterns
     */
    public function process(string $partitionKey, EventInterface $event): array
    {
        $matches = [];
        $windowSeconds = $this->pattern->getWindowSeconds();
        $eventTime = $event->getTimestamp();

        $nodes = array_values($this->pattern->getNodes());
        $nodeCount = count($nodes);
        $lastNodeName = $nodes[$nodeCount - 1]['name'];

        if (!isset($this->computationPaths[$partitionKey])) {
            $this->computationPaths[$partitionKey] = [];
        }

        $activePaths = $this->computationPaths[$partitionKey];
        $nextPaths = [];

        // 1. Evaluate existing active computation paths
        foreach ($activePaths as $path) {
            // Check time window expiration
            if ($windowSeconds !== null && ($eventTime - $path['startTimestamp']) > $windowSeconds) {
                continue; // Discard expired partial match
            }

            $currentStateName = $path['state'];
            if (!isset($this->states[$currentStateName])) {
                continue;
            }

            $currentState = $this->states[$currentStateName];

            foreach ($currentState->getTransitions() as $transition) {
                $action = $transition->getAction();
                $targetState = $transition->getTargetState();

                if ($action === NFATransition::ACTION_IGNORE) {
                    // Keep existing computation path alive for non-matching noise
                    $nextPaths[] = $path;
                } elseif ($action === NFATransition::ACTION_TAKE) {
                    // Check condition
                    if ($transition->matches($event, $path['events'])) {
                        $newEvents = $path['events'];
                        if (!isset($newEvents[$targetState])) {
                            $newEvents[$targetState] = [];
                        }
                        $newEvents[$targetState][] = $event;

                        if ($targetState === $lastNodeName) {
                            // Reached the final node in sequence -> Pattern MATCH!
                            $matches[] = new MatchedPattern(
                                $this->pattern->getName(),
                                $partitionKey,
                                $newEvents,
                                $path['startTimestamp'],
                                $eventTime
                            );
                        } else {
                            // Advance to intermediate target state
                            $nextPaths[] = [
                                'state' => $targetState,
                                'events' => $newEvents,
                                'startTimestamp' => $path['startTimestamp'],
                                'count' => 1,
                            ];
                        }
                    }
                }
            }
        }

        // 2. Try starting a NEW match from first node
        $firstNodeDef = $nodes[0];
        $firstNodeName = $firstNodeDef['name'];

        if ($firstNodeDef['condition']->evaluate($event, [])) {
            $initialEvents = [$firstNodeName => [$event]];

            if ($nodeCount === 1) {
                // Single-node pattern immediately matches!
                $matches[] = new MatchedPattern(
                    $this->pattern->getName(),
                    $partitionKey,
                    $initialEvents,
                    $eventTime,
                    $eventTime
                );
            } else {
                $nextPaths[] = [
                    'state' => $firstNodeName,
                    'events' => $initialEvents,
                    'startTimestamp' => $eventTime,
                    'count' => 1,
                ];
            }
        }

        $this->computationPaths[$partitionKey] = $nextPaths;

        return $matches;
    }

    /**
     * Clear active computation paths.
     */
    public function reset(?string $partitionKey = null): void
    {
        if ($partitionKey !== null) {
            unset($this->computationPaths[$partitionKey]);
        } else {
            $this->computationPaths = [];
        }
    }

    public function getActivePathCount(?string $partitionKey = null): int
    {
        if ($partitionKey !== null) {
            return count($this->computationPaths[$partitionKey] ?? []);
        }

        $count = 0;
        foreach ($this->computationPaths as $paths) {
            $count += count($paths);
        }
        return $count;
    }

    public function getPattern(): Pattern
    {
        return $this->pattern;
    }
}
