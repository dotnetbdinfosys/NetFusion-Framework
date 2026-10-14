<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
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

namespace NetFusion\Core\ServiceMesh\Spiffe;

use RuntimeException;

/**
 * Class WorkloadAttestor
 *
 * Implements SPIRE-style workload attestation.
 * Selectors (e.g., service, namespace, pid, uid) map to allowed SPIFFE IDs.
 */
class WorkloadAttestor
{
    /** @var array<string, array{selectors: array, spiffe_id: SpiffeId}> */
    private array $registrationEntries = [];

    public function __construct(
        private readonly string $trustDomain = 'cluster.local'
    ) {}

    public function registerWorkload(string $id, array $selectors, string|SpiffeId $spiffeId): self
    {
        $this->registrationEntries[$id] = [
            'selectors' => $selectors,
            'spiffe_id' => $spiffeId instanceof SpiffeId ? $spiffeId : new SpiffeId($spiffeId),
        ];
        return $this;
    }

    public function attest(array $workloadProperties): SpiffeId
    {
        foreach ($this->registrationEntries as $entry) {
            if ($this->matchesSelectors($entry['selectors'], $workloadProperties)) {
                return $entry['spiffe_id'];
            }
        }

        // Default automatic attestation if service name is present
        if (isset($workloadProperties['service'])) {
            $ns = $workloadProperties['namespace'] ?? 'default';
            $service = $workloadProperties['service'];
            return SpiffeId::of($this->trustDomain, "/ns/{$ns}/sa/{$service}");
        }

        throw new RuntimeException("Workload attestation failed. No matching SPIFFE ID for provided attributes.");
    }

    private function matchesSelectors(array $selectors, array $properties): bool
    {
        foreach ($selectors as $key => $expectedValue) {
            if (!isset($properties[$key])) {
                return false;
            }

            if ($expectedValue instanceof \Closure) {
                if (!$expectedValue($properties[$key])) {
                    return false;
                }
            } elseif ($properties[$key] !== $expectedValue) {
                return false;
            }
        }

        return true;
    }

    public function getRegisteredCount(): int
    {
        return count($this->registrationEntries);
    }
}
