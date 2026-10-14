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

namespace NetFusion\Core\CEP;

use JsonSerializable;
use NetFusion\Core\CEP\Contracts\CEPRuleInterface;
use NetFusion\Core\CEP\Contracts\EventInterface;
use NetFusion\Core\CEP\Contracts\SinkInterface;
use NetFusion\Core\CEP\Contracts\WindowAssignerInterface;
use NetFusion\Core\CEP\Contracts\WindowFunctionInterface;
use NetFusion\Core\CEP\Pattern\MatchedPattern;
use NetFusion\Core\CEP\Pattern\NFA;
use NetFusion\Core\CEP\Pattern\Pattern;
use NetFusion\Core\CEP\Window\WindowAggregate;
use NetFusion\Core\CEP\Window\WindowState;

class CEPRule implements CEPRuleInterface, JsonSerializable
{
    protected string $name;
    protected string $description;
    protected string $streamName;
    protected bool $enabled = true;

    /** @var callable|null Filter predicate */
    protected $filter = null;
    /** @var string|callable|null Partition key selector */
    protected $keySelector = null;

    /** @var Pattern|null */
    protected ?Pattern $pattern = null;
    /** @var NFA|null */
    protected ?NFA $nfa = null;

    /** @var WindowAssignerInterface|null */
    protected ?WindowAssignerInterface $windowAssigner = null;
    /** @var WindowFunctionInterface|null */
    protected ?WindowFunctionInterface $windowFunction = null;
    /** @var WindowState|null */
    protected ?WindowState $windowState = null;
    /** @var callable|null Post-aggregation condition */
    protected $havingCondition = null;

    /** @var array<SinkInterface|callable> */
    protected array $actions = [];

    // Runtime Metrics
    protected int $eventsProcessed = 0;
    protected int $matchesDetected = 0;
    protected int $alertsFired = 0;
    protected ?float $lastMatchedAt = null;
    protected float $createdAt;

    public function __construct(
        string $name,
        string $streamName,
        string $description = '',
        bool $enabled = true
    ) {
        $this->name = $name;
        $this->streamName = $streamName;
        $this->description = $description;
        $this->enabled = $enabled;
        $this->createdAt = microtime(true);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getStreamName(): string
    {
        return $this->streamName;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }

    public function setFilter(?callable $filter): self
    {
        $this->filter = $filter;
        return $this;
    }

    public function setKeySelector(string|callable|null $keySelector): self
    {
        $this->keySelector = $keySelector;
        return $this;
    }

    public function setPattern(?Pattern $pattern): self
    {
        $this->pattern = $pattern;
        $this->nfa = $pattern !== null ? new NFA($pattern) : null;
        return $this;
    }

    public function setWindow(
        ?WindowAssignerInterface $assigner,
        ?WindowFunctionInterface $function = null,
        ?callable $having = null
    ): self {
        $this->windowAssigner = $assigner;
        $this->windowFunction = $function;
        $this->havingCondition = $having;
        $this->windowState = ($assigner !== null && $function !== null)
            ? new WindowState($assigner, $function)
            : null;
        return $this;
    }

    public function addAction(SinkInterface|callable $action): self
    {
        $this->actions[] = $action;
        return $this;
    }

    public function process(EventInterface $event): array
    {
        if (!$this->enabled) {
            return [];
        }

        $this->eventsProcessed++;

        // 1. Filter predicate check
        if ($this->filter !== null && !call_user_func($this->filter, $event)) {
            return [];
        }

        // 2. Resolve partition key
        $partitionKey = 'global';
        if (is_string($this->keySelector)) {
            $val = $event->get($this->keySelector);
            if ($val !== null) {
                $partitionKey = (string) $val;
            }
        } elseif (is_callable($this->keySelector)) {
            $partitionKey = (string) call_user_func($this->keySelector, $event);
        }

        $results = [];

        // 3. Pattern Matching (NFA)
        if ($this->nfa !== null) {
            $matches = $this->nfa->process($partitionKey, $event);
            foreach ($matches as $match) {
                $this->matchesDetected++;
                $this->alertsFired++;
                $this->lastMatchedAt = microtime(true);
                $this->dispatchAction($match, $partitionKey);
                $results[] = $match;
            }
        }

        // 4. Window Aggregation
        if ($this->windowState !== null) {
            $winResults = $this->windowState->addEvent($partitionKey, $event, microtime(true));
            foreach ($winResults as $winRes) {
                // Check HAVING condition
                if ($this->havingCondition !== null && !call_user_func($this->havingCondition, $winRes)) {
                    continue;
                }

                $this->matchesDetected++;
                $this->alertsFired++;
                $this->lastMatchedAt = microtime(true);
                $this->dispatchAction($winRes, $partitionKey);
                $results[] = $winRes;
            }
        }

        // 5. If neither pattern nor window, simple event trigger
        if ($this->nfa === null && $this->windowState === null) {
            $this->matchesDetected++;
            $this->alertsFired++;
            $this->lastMatchedAt = microtime(true);
            $this->dispatchAction($event, $partitionKey);
            $results[] = $event;
        }

        return $results;
    }

    public function getWindowState(): ?WindowState
    {
        return $this->windowState;
    }

    public function getNFA(): ?NFA
    {
        return $this->nfa;
    }

    public function getPattern(): ?Pattern
    {
        return $this->pattern;
    }

    public function getStats(): array
    {
        return [
            'name' => $this->name,
            'stream' => $this->streamName,
            'enabled' => $this->enabled,
            'description' => $this->description,
            'events_processed' => $this->eventsProcessed,
            'matches_detected' => $this->matchesDetected,
            'alerts_fired' => $this->alertsFired,
            'last_matched_at' => $this->lastMatchedAt,
            'has_pattern' => $this->pattern !== null,
            'has_window' => $this->windowAssigner !== null,
            'active_nfa_paths' => $this->nfa !== null ? $this->nfa->getActivePathCount() : 0,
            'active_windows' => $this->windowState !== null ? $this->windowState->getActiveWindowCount() : 0,
        ];
    }

    protected function dispatchAction(mixed $output, string $partitionKey): void
    {
        $context = [
            'rule' => $this->name,
            'stream' => $this->streamName,
            'key' => $partitionKey,
            'timestamp' => microtime(true),
        ];

        foreach ($this->actions as $action) {
            if ($action instanceof SinkInterface) {
                $action->emit($output, $context);
            } elseif (is_callable($action)) {
                call_user_func($action, $output, $context);
            }
        }
    }

    public function jsonSerialize(): array
    {
        return $this->getStats();
    }
}
