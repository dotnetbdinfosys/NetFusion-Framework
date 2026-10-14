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

namespace NetFusion\Core\ServiceMesh\Routing;

use JsonSerializable;
use NetFusion\Core\ServiceMesh\Proxy\MeshRequestContext;

class TrafficSplitter implements JsonSerializable
{
    /** @var array<string, int> Subset weights, e.g. ['v1' => 90, 'v2' => 10] */
    protected array $weights = [];
    /** @var array<string, array<string, string>> Header rules, e.g. ['X-Beta-User' => ['1' => 'v2']] */
    protected array $headerRules = [];
    protected ?string $shadowSubset = null;

    /**
     * @param array<string, int> $weights
     */
    public function __construct(array $weights = ['v1' => 100])
    {
        $this->weights = $weights;
    }

    public static function weights(array $weights): self
    {
        return new self($weights);
    }

    public static function canary(string $stableVersion = 'v1', string $canaryVersion = 'v2', int $canaryPercent = 10): self
    {
        $canaryPercent = max(0, min(100, $canaryPercent));
        $stablePercent = 100 - $canaryPercent;

        return new self([
            $stableVersion => $stablePercent,
            $canaryVersion => $canaryPercent,
        ]);
    }

    public function matchHeader(string $headerName, string $headerValue, string $targetSubset): self
    {
        if (!isset($this->headerRules[$headerName])) {
            $this->headerRules[$headerName] = [];
        }
        $this->headerRules[$headerName][$headerValue] = $targetSubset;
        return $this;
    }

    public function shadowTo(?string $subset): self
    {
        $this->shadowSubset = $subset;
        return $this;
    }

    public function getShadowSubset(): ?string
    {
        return $this->shadowSubset;
    }

    /**
     * Resolve target subset/version for an incoming request.
     */
    public function resolveSubset(MeshRequestContext $context): string
    {
        // 1. Check header matching rules
        foreach ($this->headerRules as $headerName => $valueMap) {
            $headerVal = $context->getHeader($headerName);
            if ($headerVal !== null && isset($valueMap[$headerVal])) {
                return $valueMap[$headerVal];
            }
        }

        // 2. Weighted canary resolution
        $totalWeight = array_sum($this->weights);
        if ($totalWeight <= 0) {
            return array_key_first($this->weights) ?? 'v1';
        }

        $rand = random_int(1, $totalWeight);
        $running = 0;

        foreach ($this->weights as $subset => $weight) {
            $running += $weight;
            if ($rand <= $running) {
                return $subset;
            }
        }

        return array_key_last($this->weights) ?? 'v1';
    }

    public function getWeights(): array
    {
        return $this->weights;
    }

    public function toArray(): array
    {
        return [
            'weights' => $this->weights,
            'header_rules' => $this->headerRules,
            'shadow_subset' => $this->shadowSubset,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
