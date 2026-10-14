<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework — Phase 40 Test Suite
|--------------------------------------------------------------------------
|
| File      : scratch/test_phase40.php
| Module    : Tenancy
| Purpose   : Comprehensive tests for Multi-Tenancy Data Isolation,
|             Tenant Resolvers, Dynamic Context Switching, Model Traits & CLI.
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

require_once __DIR__ . '/../vendor/autoload.php';

use NetFusion\Core\Application;
use NetFusion\Core\Config\Config;
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
use NetFusion\Core\Tenancy\Tenant;
use NetFusion\Core\Tenancy\TenantManager;
use NetFusion\Core\Tenancy\Traits\BelongsToTenant;

$basePath = realpath(__DIR__ . '/..');
$app      = new Application($basePath);

$sqlitePath = __DIR__ . '/test_phase40.sqlite';
if (file_exists($sqlitePath)) {
    @unlink($sqlitePath);
}
touch($sqlitePath);

$app->make(Config::class)->set('database.default', 'sqlite');
$app->make(Config::class)->set('database.connections.sqlite.database', $sqlitePath);
$app->make(Config::class)->set('tenancy.mode', 'database');
$app->make(Config::class)->set('tenancy.database.prefix', 'tenant_db_');

/** @var DatabaseManager $db */
$db   = $app->make(DatabaseManager::class);
$conn = $db->connection('sqlite');

$passed = 0;
$failed = 0;
$total  = 0;

function t(bool $cond, string $name): void
{
    global $passed, $failed, $total;
    $total++;
    if ($cond) {
        $passed++;
        echo "  \033[32m✔\033[0m {$name}\n";
    } else {
        $failed++;
        echo "  \033[31m✖\033[0m {$name}\n";
    }
}

echo "\n=================================================================\n";
echo "  NetFusion Phase 40 — Multi-Tenant Isolation Engine Tests\n";
echo "=================================================================\n\n";

// =============================================================================
// Section 1: Tenant Entity & Value Object
// =============================================================================
echo "\033[36m[Section 1: Tenant Entity & Value Object]\033[0m\n";

$tenant1 = new Tenant(
    id: 'acme-corp',
    name: 'ACME Corporation',
    subdomain: 'acme',
    domain: 'portal.acme.com',
    data: ['plan' => 'enterprise', 'currency' => 'USD']
);

t($tenant1 instanceof TenantInterface, 'Tenant implements TenantInterface');
t($tenant1->getTenantKey() === 'acme-corp', 'Tenant::getTenantKey() returns ID');
t($tenant1->getName() === 'ACME Corporation', 'Tenant::getName() returns name');
t($tenant1->getSubdomain() === 'acme', 'Tenant::getSubdomain() returns subdomain');
t($tenant1->getDomain() === 'portal.acme.com', 'Tenant::getDomain() returns domain');
t($tenant1->getDatabaseName() === 'tenant_db_acme', 'Tenant::getDatabaseName() applies configured prefix');
t($tenant1->isActive() === true, 'Tenant is active by default');
t($tenant1->get('plan') === 'enterprise', 'Tenant metadata getter returns value');

$tenantSuspended = new Tenant(id: 'bad-corp', name: 'Bad Corp', active: false);
t($tenantSuspended->isActive() === false, 'Tenant isActive() returns false for suspended account');

// =============================================================================
// Section 2: Request Resolvers (Header, Subdomain, Path, Domain)
// =============================================================================
echo "\033[36m[Section 2: Request Resolvers]\033[0m\n";

// Header Resolver
$headerResolver = new HeaderTenantResolver();
$reqWithHeader = new Request(method: 'GET', uri: '/', server: ['HTTP_X_TENANT_ID' => 'tenant-header-99']);
t($headerResolver->resolve($reqWithHeader) === 'tenant-header-99', 'HeaderTenantResolver resolves X-Tenant-ID header');

$reqWithAltHeader = new Request(method: 'GET', uri: '/', server: ['HTTP_X_TENANT' => 'tenant-alt-88']);
t($headerResolver->resolve($reqWithAltHeader) === 'tenant-alt-88', 'HeaderTenantResolver resolves fallback X-Tenant header');

$reqNoHeader = new Request(method: 'GET', uri: '/');
t($headerResolver->resolve($reqNoHeader) === null, 'HeaderTenantResolver returns null when header missing');

// Subdomain Resolver
$subdomainResolver = new SubdomainTenantResolver();
$reqSubdomain = new Request(method: 'GET', uri: '/', server: ['HTTP_HOST' => 'acme.fusionapp.com']);
t($subdomainResolver->resolve($reqSubdomain) === 'acme', 'SubdomainTenantResolver resolves subdomain acme');

$reqExcludedSubdomain = new Request(method: 'GET', uri: '/', server: ['HTTP_HOST' => 'api.fusionapp.com']);
t($subdomainResolver->resolve($reqExcludedSubdomain) === null, 'SubdomainTenantResolver ignores system subdomains (api)');

// Path Resolver
$pathResolver = new PathTenantResolver();
$reqPath = new Request(method: 'GET', uri: '/t/globex/dashboard', server: ['REQUEST_URI' => '/t/globex/dashboard']);
t($pathResolver->resolve($reqPath) === 'globex', 'PathTenantResolver resolves /t/{tenant}/ prefix');

// Domain Resolver
$domainResolver = new DomainTenantResolver();
$reqDomain = new Request(method: 'GET', uri: '/', server: ['HTTP_HOST' => 'portal.acme.com']);
t($domainResolver->resolve($reqDomain) === 'portal.acme.com', 'DomainTenantResolver resolves custom domain host');

// =============================================================================
// Section 3: TenantManager Lifecycle & Dynamic Switching
// =============================================================================
echo "\033[36m[Section 3: TenantManager Lifecycle & Dynamic Switching]\033[0m\n";

/** @var TenantManager $tenancy */
$tenancy = $app->make(TenantManager::class);
$tenancy->registerTenant($tenant1);
$tenancy->registerTenant($tenantSuspended);

t($tenancy->hasTenant() === false, 'TenantManager starts with no active tenant');
t($tenancy->getTenant() === null, 'TenantManager::getTenant() is initially null');

$tenancy->setTenant($tenant1);
t($tenancy->hasTenant() === true, 'TenantManager has active tenant after setTenant');
t($tenancy->getTenantId() === 'acme-corp', 'TenantManager returns active tenant key acme-corp');
t($tenancy->requireTenant() === $tenant1, 'TenantManager::requireTenant() returns active tenant');

// Suspended tenant throws exception
$suspendedThrew = false;
try {
    $tenancy->setTenant($tenantSuspended);
} catch (TenantNotActiveException $e) {
    $suspendedThrew = true;
}
t($suspendedThrew === true, 'TenantManager throws TenantNotActiveException on suspended tenant');

// Scoped execution with forTenant()
$tenancy->setTenant($tenant1);
$executedTenantKey = null;
$tenancy->forTenant('globex-corp', function ($t) use (&$executedTenantKey) {
    $executedTenantKey = $t->getTenantKey();
    t($t->getTenantKey() === 'globex-corp', 'forTenant() switches context to globex-corp');
});
t($executedTenantKey === 'globex-corp', 'forTenant() executed with target tenant');
t($tenancy->getTenantId() === 'acme-corp', 'forTenant() restored previous tenant (acme-corp) in finally block');

// each() tenant iterator
$visited = [];
$tenancy->each(function ($t) use (&$visited) {
    if ($t->isActive()) {
        $visited[] = $t->getTenantKey();
    }
});
t(in_array('acme-corp', $visited, true), 'tenancy->each() visited registered active tenants');

// =============================================================================
// Section 4: Tenancy Isolation Drivers
// =============================================================================
echo "\033[36m[Section 4: Tenancy Isolation Drivers]\033[0m\n";

$dbDriver = $tenancy->driver('database');
t($dbDriver instanceof DatabaseTenancyDriver, 'DatabaseTenancyDriver resolved');
t($dbDriver->getMode() === 'database', 'DatabaseTenancyDriver reports database mode');

$colDriver = $tenancy->driver('column');
t($colDriver instanceof ColumnTenancyDriver, 'ColumnTenancyDriver resolved');
t($colDriver->getMode() === 'column', 'ColumnTenancyDriver reports column mode');

$colDriver->initialize($tenant1);
t($colDriver->getActiveTenantId() === 'acme-corp', 'ColumnTenancyDriver stores active tenant ID');
$colDriver->end();
t($colDriver->getActiveTenantId() === null, 'ColumnTenancyDriver end() resets active tenant ID');

$schemaDriver = $tenancy->driver('schema');
t($schemaDriver instanceof SchemaTenancyDriver, 'SchemaTenancyDriver resolved');
t($schemaDriver->getMode() === 'schema', 'SchemaTenancyDriver reports schema mode');

// =============================================================================
// Section 5: Model Integration with BelongsToTenant Trait
// =============================================================================
echo "\033[36m[Section 5: Model Integration with BelongsToTenant Trait]\033[0m\n";

class TenantCustomerModel
{
    use BelongsToTenant;
    public array $attributes = [];

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
        $this->initializeBelongsToTenant();
    }
}

// With active tenant
$tenancy->setTenant($tenant1);
$customer = new TenantCustomerModel(['name' => 'John Doe']);
t($customer->getTenantId() === 'acme-corp', 'BelongsToTenant trait auto-injects active tenant_id');

$customer->setTenantId('custom-tenant-99');
t($customer->getTenantId() === 'custom-tenant-99', 'BelongsToTenant setTenantId() updates tenant_id');

// =============================================================================
// Section 6: Global Helpers & Facade
// =============================================================================
echo "\033[36m[Section 6: Global Helpers & Facade]\033[0m\n";

$tenancy->setTenant($tenant1);
t(function_exists('tenant'), 'Global helper tenant() exists');
t(function_exists('tenant_id'), 'Global helper tenant_id() exists');
t(function_exists('tenancy'), 'Global helper tenancy() exists');

t(tenant() instanceof TenantInterface, 'tenant() returns active TenantInterface');
t(tenant('name') === 'ACME Corporation', 'tenant("name") returns tenant name');
t(tenant('subdomain') === 'acme', 'tenant("subdomain") returns subdomain');
t(tenant_id() === 'acme-corp', 'tenant_id() returns string ID');
t(tenancy() instanceof TenantManager, 'tenancy() returns TenantManager instance');

echo "\n=================================================================\n";
echo "  Results: {$passed}/{$total} Passed (" . round(($passed / $total) * 100) . "%)\n";
echo "=================================================================\n\n";

// Cleanup sqlite file
if (file_exists($sqlitePath)) {
    @unlink($sqlitePath);
}

if ($failed > 0) {
    exit(1);
}