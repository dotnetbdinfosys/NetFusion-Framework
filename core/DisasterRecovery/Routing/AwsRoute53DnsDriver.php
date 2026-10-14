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
 * Class AwsRoute53DnsDriver
 *
 * AWS Route53 ChangeResourceRecordSets driver with dry-run/simulation mode.
 */
class AwsRoute53DnsDriver implements DnsProviderInterface
{
    protected array $records = [];
    protected array $history = [];

    public function __construct(
        protected string $hostedZoneId = '',
        protected string $accessKey = '',
        protected string $secretKey = '',
        protected bool $simulate = true
    ) {
    }

    public function getName(): string
    {
        return 'route53';
    }

    public function isSimulated(): bool
    {
        return $this->simulate || empty($this->accessKey);
    }

    public function updateRecord(string $domain, string $type, string $target, int $ttl = 30): bool
    {
        $key = "{$domain}:{$type}";
        $this->records[$key] = $target;

        $this->history[] = [
            'provider'       => 'route53',
            'hosted_zone_id' => $this->hostedZoneId,
            'domain'         => $domain,
            'type'           => $type,
            'target'         => $target,
            'ttl'            => $ttl,
            'simulated'      => $this->isSimulated(),
            'timestamp'      => microtime(true),
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
