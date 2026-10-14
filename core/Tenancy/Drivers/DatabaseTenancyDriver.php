<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Tenancy/Drivers/DatabaseTenancyDriver.php
| Module    : Tenancy
| Namespace : NetFusion\Core\Tenancy\Drivers
| Purpose   : Separate database per tenant isolation driver.
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

namespace NetFusion\Core\Tenancy\Drivers;

use NetFusion\Core\Database\DatabaseManager;
use NetFusion\Core\Tenancy\Contracts\TenancyDriverInterface;
use NetFusion\Core\Tenancy\Contracts\TenantInterface;

class DatabaseTenancyDriver implements TenancyDriverInterface
{
    public function __construct(protected DatabaseManager $db)
    {
    }

    public function initialize(TenantInterface $tenant): void
    {
        $customConfig = $tenant->getMetadata()['db_config'] ?? [];
        $dbName = $tenant->getDatabaseName();

        if ($dbName === ':memory:' || str_ends_with($dbName, '.sqlite')) {
            $customConfig['driver'] = 'sqlite';
        }

        $this->db->setTenantConnection($tenant->getTenantKey(), array_merge($customConfig, [
            'database' => $dbName,
        ]));
    }

    public function end(): void
    {
        // Reverts to default system database
    }

    public function getMode(): string
    {
        return 'database';
    }
}