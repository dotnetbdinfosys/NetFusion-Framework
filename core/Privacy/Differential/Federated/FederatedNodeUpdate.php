<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Privacy/Differential/Federated/FederatedNodeUpdate.php
 | Author    : Md. Atiquzzaman Ashish
 | Company   : DotNetBD
 | Website   : www.dotnetbd.com
 | Copyright : © DotNetBD. All rights reserved.
 | License   : MIT
 | Version   : 1.0.0
 |
 |--------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace NetFusion\Core\Privacy\Differential\Federated;

use NetFusion\Core\Privacy\Differential\Aggregation\SensitivityAnalyzer;

/**
 * Local update sent from a federated node/device.
 */
class FederatedNodeUpdate
{
    /**
     * @param string $nodeId
     * @param float[] $weights Model weights or gradients
     * @param int $sampleCount Number of local data points
     * @param float|null $clippingNorm L2 norm threshold C
     */
    public function __construct(
        private string $nodeId,
        private array $weights,
        private int $sampleCount = 1,
        private ?float $clippingNorm = null
    ) {
        if ($this->clippingNorm !== null && $this->clippingNorm > 0.0) {
            $this->weights = SensitivityAnalyzer::clipVector($this->weights, $this->clippingNorm);
        }
    }

    public function getNodeId(): string
    {
        return $this->nodeId;
    }

    public function getWeights(): array
    {
        return $this->weights;
    }

    public function getSampleCount(): int
    {
        return $this->sampleCount;
    }

    public function getNorm(): float
    {
        return SensitivityAnalyzer::l2Norm($this->weights);
    }
}
