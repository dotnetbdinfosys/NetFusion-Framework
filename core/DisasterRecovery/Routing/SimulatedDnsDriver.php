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

/**
 * Class SimulatedDnsDriver
 *
 * In-memory DNS driver for local testing, staging, and DR drills.
 */
class SimulatedDnsDriver implements DnsProviderInterface
{
    /**
     * @var array<string, string> "domain:type" => target
     */
    protected array $records = [];

    /**
     * Audit log of updates: [ [ 'domain' => ..., 'target' => ..., 'ttl' => ..., 'time' => ... ] ]
     *
     * @var array<array>
     */
    protected array $history = [];

    public function getName(): string
    {
        return 'simulated';
    }

    public function updateRecord(string $domain, string $type, string $target, int $ttl = 30): bool
    {
        $key = "{$domain}:{$type}";
        $this->records[$key] = $target;
        $this->history[] = [
            'domain'    => $domain,
            'type'      => $type,
            'target'    => $target,
            'ttl'       => $ttl,
            'timestamp' => microtime(true),
        ];
        return true;
    }

    public function getRecord(string $domain, string $type): ?string
    {
        $key = "{$domain}:{$type}";
        return $this->records[$key] ?? null;
    }

    public function getHistory(): array
    {
        return $this->history;
    }
}
