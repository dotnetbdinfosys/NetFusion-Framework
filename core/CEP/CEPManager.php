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

use NetFusion\Core\CEP\Contracts\CEPManagerInterface;
use NetFusion\Core\CEP\Contracts\CEPRuleInterface;
use NetFusion\Core\CEP\Contracts\CEPStoreInterface;
use NetFusion\Core\CEP\Contracts\EventInterface;
use NetFusion\Core\CEP\Contracts\SourceInterface;
use NetFusion\Core\CEP\Store\ArrayCEPStore;
use NetFusion\Core\CEP\Stream\DataStream;

class CEPManager implements CEPManagerInterface
{
    /** @var array<string, DataStream> */
    protected array $streams = [];
    /** @var array<string, CEPRuleInterface> */
    protected array $rules = [];
    /** @var array<string, SourceInterface> */
    protected array $sources = [];
    protected CEPStoreInterface $store;

    protected ?Watermark $currentWatermark = null;
    protected int $totalEventsIngested = 0;
    protected int $totalAlertsEmitted = 0;
    protected float $startTime;

    public function __construct(?CEPStoreInterface $store = null)
    {
        $this->store = $store ?? new ArrayCEPStore();
        $this->startTime = microtime(true);
    }

    public function stream(string $name): DataStream
    {
        if (!isset($this->streams[$name])) {
            $this->streams[$name] = new DataStream($name);
        }
        return $this->streams[$name];
    }

    public function rule(string $name): RuleBuilder
    {
        return new class($name, $this) extends RuleBuilder {
            protected CEPManager $manager;

            public function __construct(string $name, CEPManager $manager)
            {
                parent::__construct($name);
                $this->manager = $manager;
            }

            public function register(): CEPRule
            {
                $rule = $this->build();
                $this->manager->registerRule($rule);
                return $rule;
            }
        };
    }

    public function registerRule(CEPRuleInterface $rule): self
    {
        $this->rules[$rule->getName()] = $rule;
        return $this;
    }

    public function getRule(string $name): ?CEPRuleInterface
    {
        return $this->rules[$name] ?? null;
    }

    public function getRules(): array
    {
        return $this->rules;
    }

    public function removeRule(string $name): bool
    {
        if (isset($this->rules[$name])) {
            unset($this->rules[$name]);
            return true;
        }
        return false;
    }

    public function registerSource(SourceInterface $source): self
    {
        $this->sources[$source->getName()] = $source;
        return $this;
    }

    public function getSources(): array
    {
        return $this->sources;
    }

    public function getStore(): CEPStoreInterface
    {
        return $this->store;
    }

    public function setStore(CEPStoreInterface $store): self
    {
        $this->store = $store;
        return $this;
    }

    /**
     * Ingest an event into the CEP processing engine.
     */
    public function ingest(EventInterface|array $event, ?string $type = null): array
    {
        if (!$event instanceof EventInterface) {
            $event = Event::fromArray([
                'type' => $type ?? ($event['type'] ?? 'generic'),
                'payload' => $event,
                'timestamp' => microtime(true),
            ]);
        }

        $this->totalEventsIngested++;
        $results = [];
        $streamName = $event->getType();

        // 1. Dispatch through registered streams
        if (isset($this->streams[$streamName])) {
            $streamOutputs = $this->streams[$streamName]->emit($event);
            if (!empty($streamOutputs)) {
                $results = array_merge($results, $streamOutputs);
            }
        }

        // 2. Process against matching rules (by stream name, '*' or exact match)
        foreach ($this->rules as $rule) {
            if (!$rule->isEnabled()) {
                continue;
            }

            $ruleStream = $rule->getStreamName();
            if ($ruleStream === '*' || $ruleStream === $streamName || $ruleStream === 'default') {
                $ruleMatches = $rule->process($event);
                if (!empty($ruleMatches)) {
                    foreach ($ruleMatches as $match) {
                        $this->totalAlertsEmitted++;
                        // Auto-record alert in store
                        $this->store->saveAlert([
                            'rule_name' => $rule->getName(),
                            'stream' => $streamName,
                            'partition_key' => method_exists($match, 'getPartitionKey') ? $match->getPartitionKey() : 'global',
                            'payload' => is_object($match) && method_exists($match, 'toArray') ? $match->toArray() : (array) $match,
                            'created_at' => microtime(true),
                        ]);
                    }
                    $results = array_merge($results, $ruleMatches);
                }
            }
        }

        return $results;
    }

    /**
     * Ingest a batch of events sequentially.
     */
    public function ingestBatch(array $events): array
    {
        $allResults = [];
        foreach ($events as $event) {
            $res = $this->ingest($event);
            if (!empty($res)) {
                $allResults = array_merge($allResults, $res);
            }
        }
        return $allResults;
    }

    /**
     * Poll all registered sources and ingest available events.
     */
    public function pollSources(): array
    {
        $results = [];
        foreach ($this->sources as $source) {
            $polled = $source->poll();
            if (!empty($polled)) {
                $res = $this->ingestBatch($polled);
                if (!empty($res)) {
                    $results = array_merge($results, $res);
                }
            }
        }
        return $results;
    }

    /**
     * Advance engine event-time watermark.
     */
    public function advanceWatermark(float $timestamp, float $allowedLateness = 0.0): void
    {
        $this->currentWatermark = Watermark::of($timestamp, $allowedLateness);

        foreach ($this->rules as $rule) {
            if ($rule instanceof CEPRule) {
                $ws = $rule->getWindowState();
                if ($ws !== null) {
                    $fired = $ws->advanceWatermark($this->currentWatermark);
                    foreach ($fired as $item) {
                        $this->totalAlertsEmitted++;
                        $this->store->saveAlert([
                            'rule_name' => $rule->getName(),
                            'stream' => $rule->getStreamName(),
                            'partition_key' => $item['key'] ?? 'global',
                            'payload' => $item,
                            'created_at' => microtime(true),
                        ]);
                    }
                }
            }
        }
    }

    public function getCurrentWatermark(): ?Watermark
    {
        return $this->currentWatermark;
    }

    public function getStats(): array
    {
        $rulesStats = [];
        foreach ($this->rules as $name => $rule) {
            $rulesStats[$name] = $rule->getStats();
        }

        return [
            'uptime_seconds' => round(microtime(true) - $this->startTime, 2),
            'total_events_ingested' => $this->totalEventsIngested,
            'total_alerts_emitted' => $this->totalAlertsEmitted,
            'active_streams' => count($this->streams),
            'registered_rules' => count($this->rules),
            'registered_sources' => count($this->sources),
            'rules' => $rulesStats,
        ];
    }
}
