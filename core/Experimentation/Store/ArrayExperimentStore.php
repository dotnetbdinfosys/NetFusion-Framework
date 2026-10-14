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

namespace NetFusion\Core\Experimentation\Store;

use NetFusion\Core\Experimentation\Canary\CanaryRollout;
use NetFusion\Core\Experimentation\Contracts\ExperimentStoreInterface;
use NetFusion\Core\Experimentation\Conversion;
use NetFusion\Core\Experimentation\Experiment;
use NetFusion\Core\Experimentation\Exposure;
use NetFusion\Core\Experimentation\Segment\Segment;

class ArrayExperimentStore implements ExperimentStoreInterface
{
    /** @var array<string, Experiment> */
    protected array $experiments = [];

    /** @var array<string, CanaryRollout> */
    protected array $canaries = [];

    /** @var array<string, Segment> */
    protected array $segments = [];

    /** @var list<Exposure> */
    protected array $exposures = [];

    /** @var list<Conversion> */
    protected array $conversions = [];

    public function saveExperiment(Experiment $experiment): bool
    {
        $this->experiments[$experiment->key] = $experiment;
        return true;
    }

    public function getExperiment(string $key): ?Experiment
    {
        return $this->experiments[$key] ?? null;
    }

    public function getAllExperiments(): array
    {
        return array_values($this->experiments);
    }

    public function deleteExperiment(string $key): bool
    {
        unset($this->experiments[$key]);
        return true;
    }

    public function saveCanary(CanaryRollout $canary): bool
    {
        $this->canaries[$canary->flagKey] = $canary;
        return true;
    }

    public function getCanary(string $flagKey): ?CanaryRollout
    {
        return $this->canaries[$flagKey] ?? null;
    }

    public function getAllCanaries(): array
    {
        return array_values($this->canaries);
    }

    public function saveSegment(Segment $segment): bool
    {
        $this->segments[$segment->name] = $segment;
        return true;
    }

    public function getSegment(string $name): ?Segment
    {
        return $this->segments[$name] ?? null;
    }

    public function getAllSegments(): array
    {
        return array_values($this->segments);
    }

    public function recordExposure(Exposure $exposure): bool
    {
        $this->exposures[] = $exposure;
        return true;
    }

    public function getExposures(string $experimentKey): array
    {
        $counts = [];
        $uniqueUsersPerVariant = [];

        foreach ($this->exposures as $exp) {
            if ($exp->experimentKey === $experimentKey) {
                $uniqueUsersPerVariant[$exp->variantName][$exp->identifier] = true;
            }
        }

        foreach ($uniqueUsersPerVariant as $variant => $users) {
            $counts[$variant] = count($users);
        }

        return $counts;
    }

    public function recordConversion(Conversion $conversion): bool
    {
        $this->conversions[] = $conversion;
        return true;
    }

    public function getConversions(string $experimentKey, ?string $goal = null): array
    {
        $counts = [];
        $uniqueUsers = [];

        foreach ($this->conversions as $cnv) {
            if ($cnv->experimentKey === $experimentKey) {
                if ($goal === null || $cnv->goal === $goal) {
                    $uniqueUsers[$cnv->variantName][$cnv->identifier] = true;
                }
            }
        }

        foreach ($uniqueUsers as $variant => $users) {
            $counts[$variant] = count($users);
        }

        return $counts;
    }

    public function getRevenue(string $experimentKey, ?string $goal = null): array
    {
        $revenue = [];

        foreach ($this->conversions as $cnv) {
            if ($cnv->experimentKey === $experimentKey) {
                if ($goal === null || $cnv->goal === $goal) {
                    $revenue[$cnv->variantName] = ($revenue[$cnv->variantName] ?? 0.0) + $cnv->value;
                }
            }
        }

        return $revenue;
    }
}
