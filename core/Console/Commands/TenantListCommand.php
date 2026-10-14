<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/TenantListCommand.php
| Module    : Console
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : List all registered SaaS tenants.
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

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;
use NetFusion\Core\Tenancy\TenantManager;

class TenantListCommand extends Command
{
    protected string $name = 'tenant:list';
    protected string $description = 'List all registered tenants and their isolation details.';

    public function handle(): int
    {
        /** @var TenantManager $tenancy */
        $tenancy = $this->container->get('tenant');

        $tenants = $tenancy->all();

        if (empty($tenants)) {
            $this->info('No tenants registered.');
            return 0;
        }

        $this->info(sprintf('%-20s | %-25s | %-15s | %-25s | %-8s', 'Tenant ID', 'Organization', 'Subdomain', 'Database', 'Status'));
        $this->info(str_repeat('-', 105));

        foreach ($tenants as $t) {
            $this->info(sprintf(
                '%-20s | %-25s | %-15s | %-25s | %-8s',
                $t->getTenantKey(),
                $t->getName(),
                $t->getSubdomain() ?? '-',
                $t->getDatabaseName(),
                $t->isActive() ? 'Active' : 'Suspended'
            ));
        }

        return 0;
    }
}