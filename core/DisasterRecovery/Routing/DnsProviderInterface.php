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
 * Interface DnsProviderInterface
 *
 * Contract for DNS traffic diversion drivers during Disaster Recovery.
 */
interface DnsProviderInterface
{
    public function getName(): string;

    /**
     * Update DNS record to point traffic to a new target region endpoint.
     */
    public function updateRecord(string $domain, string $type, string $target, int $ttl = 30): bool;

    /**
     * Get the current target of a DNS record.
     */
    public function getRecord(string $domain, string $type): ?string;
}
