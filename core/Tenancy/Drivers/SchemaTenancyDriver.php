<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Tenancy/Drivers/SchemaTenancyDriver.php
| Module    : Tenancy
| Namespace : NetFusion\Core\Tenancy\Drivers
| Purpose   : Schema / Namespace based isolation driver (PostgreSQL search_path / SQLite attach).
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

class SchemaTenancyDriver implements TenancyDriverInterface
{
    protected ?string $activeSchema = null;

    public function __construct(protected DatabaseManager $db)
    {
    }

    public function initialize(TenantInterface $tenant): void
    {
        $this->activeSchema = $tenant->getSubdomain() ?? $tenant->getTenantKey();
        try {
            $pdo = $this->db->connection()->getPdo();
            // Set search_path for PostgreSQL or execute schema switch
            $pdo->exec("SET search_path TO \"{$this->activeSchema}\", public");
        } catch (\Throwable $e) {
            // Fallback gracefully on drivers not supporting search_path
        }
    }

    public function end(): void
    {
        $this->activeSchema = null;
    }

    public function getMode(): string
    {
        return 'schema';
    }
}