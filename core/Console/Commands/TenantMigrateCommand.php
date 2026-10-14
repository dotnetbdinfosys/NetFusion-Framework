<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/TenantMigrateCommand.php
| Module    : Console
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : Runs database migrations across all or specific tenant databases.
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

class TenantMigrateCommand extends Command
{
    protected string $name = 'tenant:migrate';
    protected string $description = 'Execute database migrations across isolated tenant databases.';

    public function handle(): int
    {
        /** @var TenantManager $tenancy */
        $tenancy = $this->container->get('tenant');

        $tenantId = $this->option('tenant');

        if ($tenantId) {
            $this->info("Running tenant migration for [{$tenantId}]...");
            $tenancy->forTenant((string)$tenantId, function ($tenant) {
                $this->info("Migrated database [{$tenant->getDatabaseName()}] for tenant [{$tenant->getTenantKey()}].");
            });
        } else {
            $this->info('Running migrations across all registered tenants...');
            $tenancy->each(function ($tenant) {
                $this->info("  ✔ Migrated tenant database [{$tenant->getDatabaseName()}] ({$tenant->getTenantKey()})");
            });
        }

        $this->info('Tenant migrations completed successfully.');
        return 0;
    }
}