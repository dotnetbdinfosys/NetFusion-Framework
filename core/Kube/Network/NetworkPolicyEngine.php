<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\Kube\Network;

use NetFusion\Core\Kube\Models\Pod;

/**
 * Evaluates Kubernetes NetworkPolicy rules (Ingress and Egress filtering).
 */
class NetworkPolicyEngine
{
    /**
     * @var array<string, array<string, mixed>> Registered policies keyed by name
     */
    protected array $policies = [];

    /**
     * Add a network policy specification.
     *
     * @param string $name Policy identifier
     * @param array<string, mixed> $spec Policy spec (podSelector, ingress, egress, policyTypes)
     */
    public function addPolicy(string $name, array $spec): self
    {
        $this->policies[$name] = $spec;
        return $this;
    }

    public function removePolicy(string $name): self
    {
        unset($this->policies[$name]);
        return $this;
    }

    /**
     * Check if ingress traffic from source Pod to target Pod on a given port is allowed.
     */
    public function isIngressAllowed(Pod $sourcePod, Pod $targetPod, int $port = 80): bool
    {
        $targetPolicies = $this->getMatchingPolicies($targetPod);

        // If target pod is not isolated by any policy, ingress is allowed by default
        if (empty($targetPolicies)) {
            return true;
        }

        // Target is isolated: must find at least one policy rule allowing the source
        foreach ($targetPolicies as $policy) {
            $ingressRules = $policy['ingress'] ?? [];
            if (empty($ingressRules)) {
                // Empty ingress list means allow all ingress if rule is empty array [{}]
                if (isset($policy['ingress']) && $policy['ingress'] === [[]]) {
                    return true;
                }
                continue;
            }

            foreach ($ingressRules as $rule) {
                if ($this->ruleAllowsSource($rule, $sourcePod, $port)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Find policies whose podSelector matches the given Pod's labels.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function getMatchingPolicies(Pod $pod): array
    {
        $matching = [];
        $podLabels = $pod->getLabels();

        foreach ($this->policies as $name => $policy) {
            $selector = $policy['podSelector']['matchLabels'] ?? $policy['podSelector'] ?? [];
            if ($this->labelsMatch($selector, $podLabels)) {
                $matching[$name] = $policy;
            }
        }

        return $matching;
    }

    /**
     * Verify if selector key-values are a subset of pod labels.
     *
     * @param array<string, string> $selector
     * @param array<string, string> $labels
     */
    protected function labelsMatch(array $selector, array $labels): bool
    {
        foreach ($selector as $k => $v) {
            if (!isset($labels[$k]) || $labels[$k] !== $v) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if a specific ingress rule allows the source Pod and port.
     *
     * @param array<string, mixed> $rule
     */
    protected function ruleAllowsSource(array $rule, Pod $sourcePod, int $port): bool
    {
        // 1. Port match check
        if (isset($rule['ports']) && !empty($rule['ports'])) {
            $portMatched = false;
            foreach ($rule['ports'] as $p) {
                $allowedPort = (int) ($p['port'] ?? 0);
                if ($allowedPort === $port || $allowedPort === 0) {
                    $portMatched = true;
                    break;
                }
            }
            if (!$portMatched) {
                return false;
            }
        }

        // 2. From match check (podSelector, etc.)
        if (!isset($rule['from']) || empty($rule['from'])) {
            return true; // No "from" restrictions on this rule
        }

        foreach ($rule['from'] as $from) {
            $selector = $from['podSelector']['matchLabels'] ?? $from['podSelector'] ?? [];
            if (!empty($selector) && $this->labelsMatch($selector, $sourcePod->getLabels())) {
                return true;
            }

            // IP block match
            if (isset($from['ipBlock']['cidr']) && $sourcePod->getIp() !== null) {
                if ($this->ipInCidr($sourcePod->getIp(), (string) $from['ipBlock']['cidr'])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Simple CIDR matching check.
     */
    protected function ipInCidr(string $ip, string $cidr): bool
    {
        if ($cidr === '0.0.0.0/0') {
            return true;
        }

        if (!str_contains($cidr, '/')) {
            return $ip === $cidr;
        }

        [$subnet, $bits] = explode('/', $cidr, 2);
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $mask = -1 << (32 - (int) $bits);

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}
