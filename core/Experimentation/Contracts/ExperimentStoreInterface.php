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

namespace NetFusion\Core\Experimentation\Contracts;

use NetFusion\Core\Experimentation\Canary\CanaryRollout;
use NetFusion\Core\Experimentation\Conversion;
use NetFusion\Core\Experimentation\Experiment;
use NetFusion\Core\Experimentation\Exposure;
use NetFusion\Core\Experimentation\Segment\Segment;

interface ExperimentStoreInterface
{
    // Experiments
    public function saveExperiment(Experiment $experiment): bool;
    public function getExperiment(string $key): ?Experiment;
    /** @return list<Experiment> */
    public function getAllExperiments(): array;
    public function deleteExperiment(string $key): bool;

    // Canary Rollouts
    public function saveCanary(CanaryRollout $canary): bool;
    public function getCanary(string $flagKey): ?CanaryRollout;
    /** @return list<CanaryRollout> */
    public function getAllCanaries(): array;

    // Segments
    public function saveSegment(Segment $segment): bool;
    public function getSegment(string $name): ?Segment;
    /** @return list<Segment> */
    public function getAllSegments(): array;

    // Exposures & Conversions
    public function recordExposure(Exposure $exposure): bool;
    /** @return array<string, int> Map of variantName => exposureCount */
    public function getExposures(string $experimentKey): array;

    public function recordConversion(Conversion $conversion): bool;
    /** @return array<string, int> Map of variantName => conversionCount */
    public function getConversions(string $experimentKey, ?string $goal = null): array;
    /** @return array<string, float> Map of variantName => totalRevenue */
    public function getRevenue(string $experimentKey, ?string $goal = null): array;
}
