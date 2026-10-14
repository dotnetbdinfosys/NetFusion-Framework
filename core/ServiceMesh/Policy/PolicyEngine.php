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

namespace NetFusion\Core\ServiceMesh\Policy;

use NetFusion\Core\ServiceMesh\Spiffe\SpiffeId;

/**
 * Class PolicyEngine
 *
 * Coordinates and executes mesh security policies across the service mesh.
 */
class PolicyEngine
{
    /** @var array<string, MeshPolicy> */
    private array $policies = [];
    private array $auditLog = [];

    public function registerPolicy(MeshPolicy $policy): self
    {
        $this->policies[$policy->getName()] = $policy;
        return $this;
    }

    public function getPolicy(string $name): ?MeshPolicy
    {
        return $this->policies[$name] ?? null;
    }

    public function authorize(
        ServiceIdentity $source,
        string|SpiffeId $target,
        string $method = 'GET',
        string $path = '/',
        array $headers = []
    ): AccessDecision {
        $targetId = $target instanceof SpiffeId ? $target : new SpiffeId($target);

        // Find policy applicable to the target service or global default
        $targetServiceName = $targetId->getWorkloadName();
        $policy = $this->policies[$targetServiceName] ?? $this->policies['global'] ?? null;

        if ($policy === null) {
            $decision = AccessDecision::deny('', "No policy found for target '{$targetServiceName}' (Zero-Trust Default Deny)");
        } else {
            $decision = $policy->evaluate($source, $targetId, $method, $path, $headers);
        }

        $this->recordAudit([
            'source' => (string) $source->getSpiffeId(),
            'target' => (string) $targetId,
            'method' => strtoupper($method),
            'path' => $path,
            'allowed' => $decision->isAllowed(),
            'rule' => $decision->getMatchedRule(),
            'reason' => $decision->getReason(),
            'timestamp' => time(),
        ]);

        return $decision;
    }

    private function recordAudit(array $record): void
    {
        $this->auditLog[] = $record;
        if (count($this->auditLog) > 1000) {
            array_shift($this->auditLog);
        }
    }

    public function getAuditLog(int $limit = 50): array
    {
        return array_slice($this->auditLog, -$limit);
    }

    public function getPolicyCount(): int
    {
        return count($this->policies);
    }
}
