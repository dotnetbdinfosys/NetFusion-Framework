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

namespace NetFusion\Core\DisasterRecovery\Routing;

use RuntimeException;

/**
 * Class TrafficRouter
 *
 * Coordinates DNS and traffic redirection across multi-cloud regions.
 */
class TrafficRouter
{
    /**
     * @var array<string, array{primary: string, standbys: array<string, string>, current: string, canary_percent: int}>
     */
    protected array $domains = [];

    public function __construct(
        protected DnsProviderInterface $dnsProvider
    ) {
    }

    public function getDnsProvider(): DnsProviderInterface
    {
        return $this->dnsProvider;
    }

    public function setDnsProvider(DnsProviderInterface $dnsProvider): self
    {
        $this->dnsProvider = $dnsProvider;
        return $this;
    }

    /**
     * Register a domain with its primary and standby region targets.
     *
     * @param string $domain e.g. "api.netfusion.io"
     * @param string $primaryTarget e.g. "us-east-1.netfusion.io"
     * @param array<string, string> $standbyTargets e.g. [ 'eu-west-1' => 'eu-west-1.netfusion.io' ]
     * @return self
     */
    public function registerDomain(string $domain, string $primaryTarget, array $standbyTargets = []): self
    {
        $this->domains[$domain] = [
            'primary'        => $primaryTarget,
            'standbys'       => $standbyTargets,
            'current'        => $primaryTarget,
            'canary_percent' => 0,
        ];

        // Seed initial DNS record
        $this->dnsProvider->updateRecord($domain, 'CNAME', $primaryTarget, 60);

        return $this;
    }

    public function hasDomain(string $domain): bool
    {
        return isset($this->domains[$domain]);
    }

    public function getCurrentTarget(string $domain): ?string
    {
        return $this->domains[$domain]['current'] ?? null;
    }

    /**
     * Divert 100% of domain traffic to a target region endpoint.
     */
    public function divertTraffic(string $domain, string $targetEndpoint, int $ttl = 30): bool
    {
        if (!isset($this->domains[$domain])) {
            throw new RuntimeException("Domain '{$domain}' not registered with TrafficRouter");
        }

        $ok = $this->dnsProvider->updateRecord($domain, 'CNAME', $targetEndpoint, $ttl);
        if ($ok) {
            $this->domains[$domain]['current'] = $targetEndpoint;
            $this->domains[$domain]['canary_percent'] = 0;
        }

        return $ok;
    }

    /**
     * Configure canary traffic split percentage (0 - 100%).
     */
    public function setCanary(string $domain, int $percent): void
    {
        if (!isset($this->domains[$domain])) {
            throw new RuntimeException("Domain '{$domain}' not registered with TrafficRouter");
        }
        $this->domains[$domain]['canary_percent'] = max(0, min(100, $percent));
    }

    public function getDomainState(string $domain): ?array
    {
        return $this->domains[$domain] ?? null;
    }

    public function getDomains(): array
    {
        return $this->domains;
    }
}
