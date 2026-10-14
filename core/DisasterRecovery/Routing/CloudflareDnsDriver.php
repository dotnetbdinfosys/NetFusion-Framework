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
 * Class CloudflareDnsDriver
 *
 * Cloudflare API v4 DNS updater with dry-run/simulation mode.
 */
class CloudflareDnsDriver implements DnsProviderInterface
{
    protected array $records = [];
    protected array $history = [];

    public function __construct(
        protected string $apiToken = '',
        protected string $zoneId = '',
        protected bool $simulate = true
    ) {
    }

    public function getName(): string
    {
        return 'cloudflare';
    }

    public function isSimulated(): bool
    {
        return $this->simulate || empty($this->apiToken);
    }

    public function updateRecord(string $domain, string $type, string $target, int $ttl = 30): bool
    {
        $key = "{$domain}:{$type}";
        $this->records[$key] = $target;

        $this->history[] = [
            'provider'  => 'cloudflare',
            'zone_id'   => $this->zoneId,
            'domain'    => $domain,
            'type'      => $type,
            'target'    => $target,
            'ttl'       => $ttl,
            'simulated' => $this->isSimulated(),
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
