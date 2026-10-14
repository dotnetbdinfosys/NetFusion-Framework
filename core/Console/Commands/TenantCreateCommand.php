<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/TenantCreateCommand.php
| Module    : Console
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : Provision a new tenant in the multi-tenant system.
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
use NetFusion\Core\Tenancy\Tenant;
use NetFusion\Core\Tenancy\TenantManager;

class TenantCreateCommand extends Command
{
    protected string $name = 'tenant:create';
    protected string $description = 'Create/register a new tenant organization.';

    public function handle(): int
    {
        $id = $this->argument('id');
        $name = $this->argument('name');

        if (!$id || !$name) {
            $this->error('Usage: php netfusion tenant:create <id> <name> [--subdomain=...] [--database=...]');
            return 1;
        }

        $subdomain = $this->option('subdomain') ? (string)$this->option('subdomain') : (string)$id;
        $database = $this->option('database') ? (string)$this->option('database') : null;

        /** @var TenantManager $tenancy */
        $tenancy = $this->container->get('tenant');

        $tenant = new Tenant(
            id: (string)$id,
            name: (string)$name,
            subdomain: $subdomain,
            database: $database,
        );

        $tenancy->registerTenant($tenant);

        $this->info("Tenant [{$name}] ({$id}) registered successfully with database [{$tenant->getDatabaseName()}].");
        return 0;
    }
}