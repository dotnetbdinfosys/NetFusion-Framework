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

namespace NetFusion\Core\Experimentation;

use NetFusion\Core\Application;
use NetFusion\Core\Experimentation\Canary\CanaryRollout;
use NetFusion\Core\Experimentation\Contracts\ExperimentStoreInterface;
use NetFusion\Core\Experimentation\Segment\Segment;
use NetFusion\Core\Experimentation\Statistics\StatisticalEngine;
use NetFusion\Core\Experimentation\Store\ArrayExperimentStore;

class ExperimentManager
{
    /** @var array<string, string> In-memory map of user identifier => last assigned variant per experiment */
    protected array $runtimeAssignments = [];

    public function __construct(
        protected ExperimentStoreInterface $store = new ArrayExperimentStore(),
        protected ?Application $app = null
    ) {}

    public function setStore(ExperimentStoreInterface $store): self
    {
        $this->store = $store;
        return $this;
    }

    public function getStore(): ExperimentStoreInterface
    {
        return $this->store;
    }

    // =========================================================================
    //  Experimentation Core & A/B/n Testing
    // =========================================================================

    /**
     * Register or update an experiment.
     */
    public function define(Experiment $experiment): self
    {
        $this->store->saveExperiment($experiment);
        return $this;
    }

    /**
     * Get an experiment by key.
     */
    public function getExperiment(string $key): ?Experiment
    {
        return $this->store->getExperiment($key);
    }

    /**
     * Get all registered experiments.
     *
     * @return list<Experiment>
     */
    public function all(): array
    {
        return $this->store->getAllExperiments();
    }

    /**
     * Delete an experiment.
     */
    public function delete(string $key): bool
    {
        return $this->store->deleteExperiment($key);
    }

    /**
     * Evaluate an experiment against context, assign variant, and optionally record exposure.
     */
    public function evaluate(string $experimentKey, mixed $context = null, bool $autoTrackExposure = true): ?Variant
    {
        $exp = $this->getExperiment($experimentKey);
        if ($exp === null) {
            return null;
        }

        $evalCtx = EvaluationContext::from($context);
        $variant = $exp->assignVariant($evalCtx);

        if ($variant !== null) {
            $identifier = $evalCtx->getIdentifier();
            $cacheKey = $experimentKey . ':' . $identifier;
            $this->runtimeAssignments[$cacheKey] = $variant->name;

            if ($autoTrackExposure && $exp->isRunning()) {
                $exposure = Exposure::create($experimentKey, $variant->name, $evalCtx);
                $this->store->recordExposure($exposure);
            }
        }

        return $variant;
    }

    /**
     * Get assigned variant name for experiment.
     */
    public function variant(string $experimentKey, mixed $context = null, string $default = 'control'): string
    {
        $v = $this->evaluate($experimentKey, $context);
        return $v !== null ? $v->name : $default;
    }

    /**
     * Get assigned variant payload for experiment.
     *
     * @return array<string, mixed>
     */
    public function payload(string $experimentKey, mixed $context = null): array
    {
        $v = $this->evaluate($experimentKey, $context);
        return $v !== null ? $v->payload : [];
    }

    /**
     * Manually record an exposure event.
     */
    public function trackExposure(string $experimentKey, string $variantName, mixed $context = null): Exposure
    {
        $evalCtx = EvaluationContext::from($context);
        $exposure = Exposure::create($experimentKey, $variantName, $evalCtx);
        $this->store->recordExposure($exposure);
        return $exposure;
    }

    /**
     * Record a conversion event for an experiment and variant.
     */
    public function trackConversion(
        string $experimentKey,
        string $variantName,
        string $identifier,
        string $goal,
        float $value = 1.0,
        array $metadata = []
    ): Conversion {
        $conversion = Conversion::create($experimentKey, $variantName, $identifier, $goal, $value, $metadata);
        $this->store->recordConversion($conversion);
        return $conversion;
    }

    /**
     * Track a goal event for the currently evaluated subject.
     */
    public function trackGoal(
        string $goal,
        float $value = 1.0,
        mixed $context = null,
        ?string $experimentKey = null,
        array $metadata = []
    ): void {
        $evalCtx = EvaluationContext::from($context);
        $identifier = $evalCtx->getIdentifier();

        // If specific experimentKey provided
        if ($experimentKey !== null) {
            $cacheKey = $experimentKey . ':' . $identifier;
            $variantName = $this->runtimeAssignments[$cacheKey] ?? null;
            if ($variantName === null) {
                $v = $this->evaluate($experimentKey, $evalCtx, false);
                $variantName = $v?->name ?? 'control';
            }
            $this->trackConversion($experimentKey, $variantName, $identifier, $goal, $value, $metadata);
            return;
        }

        // Track against all experiments that have this goal
        foreach ($this->all() as $exp) {
            if (in_array($goal, $exp->goals, true)) {
                $cacheKey = $exp->key . ':' . $identifier;
                $variantName = $this->runtimeAssignments[$cacheKey] ?? null;
                if ($variantName === null) {
                    $v = $this->evaluate($exp->key, $evalCtx, false);
                    $variantName = $v?->name ?? 'control';
                }
                $this->trackConversion($exp->key, $variantName, $identifier, $goal, $value, $metadata);
            }
        }
    }

    /**
     * Calculate statistical results for an experiment.
     *
     * @return array<string, mixed>
     */
    public function getResults(string $experimentKey, ?string $goal = null, float $alpha = 0.05): array
    {
        $exposures = $this->store->getExposures($experimentKey);
        $conversions = $this->store->getConversions($experimentKey, $goal);
        $revenue = $this->store->getRevenue($experimentKey, $goal);

        return StatisticalEngine::analyze($exposures, $conversions, $revenue, 'control', $alpha);
    }

    /**
     * Conclude an experiment and declare a winner variant.
     */
    public function conclude(string $experimentKey, ?string $winnerVariant = null): bool
    {
        $exp = $this->getExperiment($experimentKey);
        if ($exp === null) {
            return false;
        }

        if ($winnerVariant === null) {
            $results = $this->getResults($experimentKey);
            $winnerVariant = $results['winner'] ?? 'control';
        }

        $exp->status = Experiment::STATUS_CONCLUDED;
        $exp->winnerVariant = $winnerVariant;

        return $this->store->saveExperiment($exp);
    }

    // =========================================================================
    //  Canary Rollouts
    // =========================================================================

    /**
     * Register a canary rollout.
     */
    public function defineCanary(CanaryRollout $canary): self
    {
        $this->store->saveCanary($canary);
        return $this;
    }

    /**
     * Get a canary rollout by flag key.
     */
    public function getCanary(string $flagKey): ?CanaryRollout
    {
        return $this->store->getCanary($flagKey);
    }

    /**
     * Evaluate canary rollout for context.
     */
    public function canary(string $flagKey, mixed $context = null): bool
    {
        $canary = $this->getCanary($flagKey);
        if ($canary === null) {
            return false;
        }

        $evalCtx = EvaluationContext::from($context);
        return $canary->evaluate($evalCtx);
    }

    /**
     * Trigger emergency kill switch on canary.
     */
    public function killCanary(string $flagKey): bool
    {
        $canary = $this->getCanary($flagKey);
        if ($canary === null) {
            return false;
        }

        $canary->triggerKillSwitch();
        return $this->store->saveCanary($canary);
    }

    /**
     * Reset emergency kill switch on canary.
     */
    public function resetCanary(string $flagKey): bool
    {
        $canary = $this->getCanary($flagKey);
        if ($canary === null) {
            return false;
        }

        $canary->resetKillSwitch();
        return $this->store->saveCanary($canary);
    }

    // =========================================================================
    //  Targeting Segments
    // =========================================================================

    /**
     * Register a segment definition.
     */
    public function defineSegment(Segment $segment): self
    {
        $this->store->saveSegment($segment);
        return $this;
    }

    /**
     * Get a segment by name.
     */
    public function getSegment(string $name): ?Segment
    {
        return $this->store->getSegment($name);
    }

    /**
     * Check if context belongs to segment.
     */
    public function inSegment(string $segmentName, mixed $context = null): bool
    {
        $segment = $this->getSegment($segmentName);
        if ($segment === null) {
            return false;
        }

        $evalCtx = EvaluationContext::from($context);
        return $segment->matches($evalCtx);
    }
}
