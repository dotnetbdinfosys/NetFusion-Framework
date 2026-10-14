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

namespace NetFusion\Core\CEP\Contracts;

use NetFusion\Core\CEP\Stream\DataStream;
use NetFusion\Core\CEP\RuleBuilder;

interface CEPManagerInterface
{
    /**
     * Create a new event data stream.
     */
    public function stream(string $name): DataStream;

    /**
     * Create a fluent CEP rule builder.
     */
    public function rule(string $name): RuleBuilder;

    /**
     * Register a CEP rule.
     */
    public function registerRule(CEPRuleInterface $rule): self;

    /**
     * Get a registered rule by name.
     */
    public function getRule(string $name): ?CEPRuleInterface;

    /**
     * Get all registered rules.
     *
     * @return array<string, CEPRuleInterface>
     */
    public function getRules(): array;

    /**
     * Ingest an event into the CEP processing engine.
     *
     * @param EventInterface|array<string, mixed> $event
     * @param string|null $type Event type if passing raw array
     * @return array<mixed> Matches/alerts emitted across active rules
     */
    public function ingest(EventInterface|array $event, ?string $type = null): array;

    /**
     * Ingest a batch of events.
     *
     * @param array<EventInterface|array<string, mixed>> $events
     * @return array<mixed> Emitted results
     */
    public function ingestBatch(array $events): array;

    /**
     * Advance engine event-time watermark.
     */
    public function advanceWatermark(float $timestamp): void;

    /**
     * Get aggregate runtime statistics.
     *
     * @return array<string, mixed>
     */
    public function getStats(): array;
}
