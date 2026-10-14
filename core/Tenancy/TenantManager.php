<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Tenancy/TenantManager.php
| Module    : Tenancy
| Namespace : NetFusion\Core\Tenancy
| Purpose   : Enterprise Multi-Tenant Manager with multi-driver isolation.
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

namespace NetFusion\Core\Tenancy;

use Closure;
use NetFusion\Core\Application;
use NetFusion\Core\Database\DatabaseManager;
use NetFusion\Core\Http\Request;
use NetFusion\Core\Tenancy\Contracts\TenancyDriverInterface;
use NetFusion\Core\Tenancy\Contracts\TenantInterface;
use NetFusion\Core\Tenancy\Contracts\TenantResolverInterface;
use NetFusion\Core\Tenancy\Drivers\ColumnTenancyDriver;
use NetFusion\Core\Tenancy\Drivers\DatabaseTenancyDriver;
use NetFusion\Core\Tenancy\Drivers\SchemaTenancyDriver;
use NetFusion\Core\Tenancy\Exceptions\TenantNotActiveException;
use NetFusion\Core\Tenancy\Exceptions\TenantNotFoundException;
use NetFusion\Core\Tenancy\Resolvers\DomainTenantResolver;
use NetFusion\Core\Tenancy\Resolvers\HeaderTenantResolver;
use NetFusion\Core\Tenancy\Resolvers\PathTenantResolver;
use NetFusion\Core\Tenancy\Resolvers\SubdomainTenantResolver;
use RuntimeException;

class TenantManager
{
    protected ?TenantInterface $currentTenant = null;

    /**
     * @var array<string, TenantInterface>
     */
    protected array $tenants = [];

    /**
     * @var array<TenantResolverInterface>
     */
    protected array $resolvers = [];

    /**
     * @var array<string, TenancyDriverInterface>
     */
    protected array $drivers = [];

    protected ?string $defaultDriver = null;

    public function __construct(
        protected readonly Application     $app,
        protected readonly DatabaseManager $db
    ) {
        $this->registerDefaultResolvers();
    }

    protected function registerDefaultResolvers(): void
    {
        $this->resolvers = [
            new HeaderTenantResolver(),
            new SubdomainTenantResolver(),
            new PathTenantResolver(),
            new DomainTenantResolver(),
        ];
    }

    public function addResolver(TenantResolverInterface $resolver): self
    {
        array_unshift($this->resolvers, $resolver);
        return $this;
    }

    public function driver(?string $mode = null): TenancyDriverInterface
    {
        $mode = $mode ?? $this->getDefaultDriver();

        if (!isset($this->drivers[$mode])) {
            $this->drivers[$mode] = match ($mode) {
                'database' => new DatabaseTenancyDriver($this->db),
                'column'   => new ColumnTenancyDriver(),
                'schema'   => new SchemaTenancyDriver($this->db),
                default    => new DatabaseTenancyDriver($this->db),
            };
        }

        return $this->drivers[$mode];
    }

    public function getDefaultDriver(): string
    {
        /** @var \NetFusion\Core\Config\Config $config */
        $config = $this->app->make('config');
        return (string)$config->get('tenancy.mode', 'database');
    }

    public function registerTenant(TenantInterface $tenant): self
    {
        $this->tenants[$tenant->getTenantKey()] = $tenant;
        if ($tenant->getSubdomain()) {
            $this->tenants[$tenant->getSubdomain()] = $tenant;
        }
        if ($tenant->getDomain()) {
            $this->tenants[$tenant->getDomain()] = $tenant;
        }
        return $this;
    }

    public function identify(Request $request): ?TenantInterface
    {
        foreach ($this->resolvers as $resolver) {
            $identifier = $resolver->resolve($request);
            if ($identifier !== null && $identifier !== '') {
                return $this->resolveTenant($identifier);
            }
        }

        return null;
    }

    public function resolveTenant(string $identifier): ?TenantInterface
    {
        if (isset($this->tenants[$identifier])) {
            return $this->tenants[$identifier];
        }

        // Auto-factory fallback
        $tenant = new Tenant(
            id       : $identifier,
            name     : ucfirst($identifier) . ' Organization',
            subdomain: $identifier,
            database : ((string)config('tenancy.database.prefix', 'tenant_')) . $identifier,
        );

        $this->registerTenant($tenant);
        return $tenant;
    }

    public function setTenant(?TenantInterface $tenant): void
    {
        if ($tenant !== null && !$tenant->isActive()) {
            throw TenantNotActiveException::forTenant($tenant->getTenantKey());
        }

        $this->currentTenant = $tenant;

        if ($tenant !== null) {
            $this->driver()->initialize($tenant);
        } else {
            $this->driver()->end();
        }
    }

    public function getTenant(): ?TenantInterface
    {
        return $this->currentTenant;
    }

    public function getTenantId(): ?string
    {
        return $this->currentTenant?->getTenantKey();
    }

    public function hasTenant(): bool
    {
        return $this->currentTenant !== null;
    }

    public function requireTenant(): TenantInterface
    {
        if ($this->currentTenant === null) {
            throw new RuntimeException('This operation requires an active tenant context.');
        }

        return $this->currentTenant;
    }

    /**
     * Executes a callback within a specific tenant context and restores previous tenant.
     */
    public function forTenant(TenantInterface|string $tenant, callable $callback): mixed
    {
        $previousTenant = $this->currentTenant;

        $targetTenant = is_string($tenant) ? $this->resolveTenant($tenant) : $tenant;
        if (!$targetTenant) {
            throw TenantNotFoundException::forIdentifier(is_string($tenant) ? $tenant : 'unknown');
        }

        $this->setTenant($targetTenant);

        try {
            return $callback($targetTenant);
        } finally {
            $this->setTenant($previousTenant);
        }
    }

    /**
     * Run a callback for each registered tenant.
     */
    public function each(callable $callback, bool $onlyActive = true): void
    {
        // Unique list of tenants
        $unique = [];
        foreach ($this->tenants as $t) {
            $unique[$t->getTenantKey()] = $t;
        }

        foreach ($unique as $t) {
            if ($onlyActive && !$t->isActive()) {
                continue;
            }
            $this->forTenant($t, $callback);
        }
    }

    /**
     * @return array<string, TenantInterface>
     */
    public function all(): array
    {
        $unique = [];
        foreach ($this->tenants as $t) {
            $unique[$t->getTenantKey()] = $t;
        }
        return array_values($unique);
    }
}