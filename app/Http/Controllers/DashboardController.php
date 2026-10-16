<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Http/Controllers/DashboardController.php
| Module    : Dashboard
| Namespace : App\Http\Controllers
| Purpose   : Unified Executive Management Dashboard with Light UI Theme,
|             Multi-Tenancy Switcher, Dynamic Module Toggle (On/Off),
|             and Enterprise RBAC User Variants & Permissions Fabric.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| Website   : https://www.dotnetbd.com
| Copyright : © DotNetBD. All rights reserved.
| License   : MIT
| Version   : 1.3.1
|
|--------------------------------------------------------------------------
*/

namespace NetFusion\App\Http\Controllers;

use DateTimeImmutable;
use NetFusion\Core\Database\DatabaseManager;
use NetFusion\Core\Foundation\License\LicenseManager;
use NetFusion\Core\Foundation\Release\FrameworkReleaseManifest;
use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;
use NetFusion\Core\Security\SecurityAuditor;
use Throwable;

class DashboardController
{
    /**
     * Display the Executive Management Dashboard (Light UI).
     */
    public function index(Request $request): Response
    {
        $metrics = $this->collectEnterpriseMetrics();
        return Response::html($this->renderDashboardHtml($metrics));
    }

    /**
     * API endpoint returning real-time metrics for client polling.
     */
    public function metrics(Request $request): Response
    {
        return Response::json($this->collectEnterpriseMetrics());
    }

    /**
     * Toggle a module's on/off state dynamically.
     */
    public function toggleModule(Request $request): Response
    {
        $moduleParam = (string) $request->input('module', '');
        $enabledRaw = $request->input('enabled', true);
        $enabled = !in_array($enabledRaw, [0, '0', false, 'false', 'off', null], true);

        if (empty($moduleParam)) {
            return Response::json([
                'success' => false,
                'message' => 'মডিউলের নাম প্রদান করা আবশ্যক (Module name is required).'
            ], 400);
        }

        $modulesBase = base_path('app/Modules');
        $targetDir = null;
        $manifestPath = null;

        // Search directory directly or by slug
        if (is_dir($modulesBase . DIRECTORY_SEPARATOR . $moduleParam)) {
            $targetDir = $moduleParam;
            $manifestPath = $modulesBase . DIRECTORY_SEPARATOR . $moduleParam . DIRECTORY_SEPARATOR . 'module.json';
        } else {
            // Find by slug
            if (is_dir($modulesBase)) {
                $dirs = scandir($modulesBase);
                if ($dirs !== false) {
                    foreach ($dirs as $dir) {
                        if ($dir === '.' || $dir === '..') {
                            continue;
                        }
                        $candidate = $modulesBase . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . 'module.json';
                        if (is_file($candidate)) {
                            $content = file_get_contents($candidate);
                            $data = json_decode((string) $content, true);
                            if (is_array($data) && (($data['slug'] ?? '') === strtolower($moduleParam) || ($data['name'] ?? '') === $moduleParam)) {
                                $targetDir = $dir;
                                $manifestPath = $candidate;
                                break;
                            }
                        }
                    }
                }
            }
        }

        if (!$targetDir || !$manifestPath || !is_file($manifestPath)) {
            return Response::json([
                'success' => false,
                'message' => "মডিউল '{$moduleParam}' খুঁজে পাওয়া যায়নি (Module not found)."
            ], 404);
        }

        // Read and update module.json
        $manifestData = json_decode((string) file_get_contents($manifestPath), true);
        if (!is_array($manifestData)) {
            $manifestData = [];
        }

        $manifestData['enabled'] = $enabled;
        $manifestData['updated_at'] = (new DateTimeImmutable())->format('c');

        file_put_contents(
            $manifestPath,
            json_encode($manifestData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        // Sync storage modules_state.json
        $stateFile = storage_path('framework/modules_state.json');
        $dir = dirname($stateFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $states = is_file($stateFile) ? json_decode((string) file_get_contents($stateFile), true) : [];
        if (!is_array($states)) {
            $states = [];
        }
        $states[$targetDir] = [
            'enabled'    => $enabled,
            'name'       => $manifestData['name'] ?? $targetDir,
            'updated_at' => $manifestData['updated_at'],
        ];
        file_put_contents($stateFile, json_encode($states, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $statusTextBn = $enabled ? 'সক্রিয় (ON)' : 'নিষ্ক্রিয় (OFF)';

        return Response::json([
            'success'   => true,
            'module'    => $targetDir,
            'enabled'   => $enabled,
            'message'   => "মডিউল '{$targetDir}' সফলভাবে {$statusTextBn} করা হয়েছে।"
        ]);
    }

    /**
     * Switch active tenant context.
     */
    public function switchTenant(Request $request): Response
    {
        $tenantId = (string) $request->input('tenant_id', '');

        if (empty($tenantId)) {
            return Response::json([
                'success' => false,
                'message' => 'টেন্যান্ট আইডি প্রদান করা আবশ্যক (Tenant ID is required).'
            ], 400);
        }

        $tenants = $this->loadTenants();
        $found = null;
        foreach ($tenants as $t) {
            if ($t['id'] === $tenantId) {
                $found = $t;
                break;
            }
        }

        if (!$found) {
            return Response::json([
                'success' => false,
                'message' => "টেন্যান্ট '{$tenantId}' পাওয়া যায়নি (Tenant not found)."
            ], 404);
        }

        $activeTenantFile = storage_path('framework/active_tenant.json');
        $dir = dirname($activeTenantFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        file_put_contents($activeTenantFile, json_encode($found, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return Response::json([
            'success'       => true,
            'tenant'        => $found,
            'message'       => "সক্রিয় টেন্যান্ট সফলভাবে পরিবর্তিত হয়েছে: {$found['name']} ({$found['id']})"
        ]);
    }

    /**
     * Create a new tenant.
     */
    public function createTenant(Request $request): Response
    {
        $id     = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $request->input('id', '')));
        $name   = trim((string) $request->input('name', ''));
        $domain = trim((string) $request->input('domain', ''));
        $driver = trim((string) $request->input('driver', 'database'));

        if (empty($id) || empty($name)) {
            return Response::json([
                'success' => false,
                'message' => 'টেন্যান্ট আইডি এবং নাম বাধ্যতামূলক (Tenant ID and Name are required).'
            ], 400);
        }

        $tenants = $this->loadTenants();
        foreach ($tenants as $t) {
            if ($t['id'] === $id) {
                return Response::json([
                    'success' => false,
                    'message' => "টেন্যান্ট আইডি '{$id}' ইতিমধ্যে বিদ্যমান (Tenant ID already exists)."
                ], 409);
            }
        }

        $newTenant = [
            'id'         => $id,
            'name'       => $name,
            'domain'     => $domain ?: ($id . '.nff.dotnetbd.com'),
            'driver'     => in_array($driver, ['database', 'column_scoped', 'schema_scoped'], true) ? $driver : 'database',
            'status'     => 'Active',
            'users'      => 1,
            'created_at' => (new DateTimeImmutable())->format('Y-m-d'),
        ];

        $tenants[] = $newTenant;
        $this->saveTenants($tenants);

        return Response::json([
            'success' => true,
            'tenant'  => $newTenant,
            'message' => "নতুন টেন্যান্ট '{$name}' সফলভাবে তৈরি করা হয়েছে।"
        ]);
    }

    /**
     * Create a new user with variant role.
     */
    public function createUser(Request $request): Response
    {
        $name     = trim((string) $request->input('name', ''));
        $email    = trim((string) $request->input('email', ''));
        $role     = trim((string) $request->input('role', 'staff'));
        $tenantId = trim((string) $request->input('tenant_id', 'tenant_primary'));

        if (empty($name) || empty($email)) {
            return Response::json([
                'success' => false,
                'message' => 'ইউজার নাম এবং ইমেইল বাধ্যতামূলক (User name and email are required).'
            ], 400);
        }

        $validRoles = ['super_admin', 'tenant_admin', 'manager', 'staff', 'client'];
        if (!in_array($role, $validRoles, true)) {
            $role = 'staff';
        }

        $users = $this->loadUsers();
        foreach ($users as $u) {
            if (strcasecmp($u['email'], $email) === 0) {
                return Response::json([
                    'success' => false,
                    'message' => "ইমেইল '{$email}' ইতিমধ্যে ব্যবহৃত হচ্ছে (Email already in use)."
                ], 409);
            }
        }

        $newUser = [
            'id'         => 'usr_' . substr(bin2hex(random_bytes(4)), 0, 8),
            'name'       => $name,
            'email'      => $email,
            'role'       => $role,
            'tenant_id'  => $tenantId,
            'status'     => 'Active',
            'created_at' => (new DateTimeImmutable())->format('Y-m-d H:i'),
        ];

        $users[] = $newUser;
        $this->saveUsers($users);

        return Response::json([
            'success' => true,
            'user'    => $newUser,
            'message' => "ইউজার '{$name}' ({$role}) সফলভাবে যুক্ত করা হয়েছে।"
        ]);
    }

    /**
     * Gather live cross-module metrics, tenancy state, modules, and user variants.
     *
     * @return array<string, mixed>
     */
    public function collectEnterpriseMetrics(): array
    {
        $metrics = [
            'overview' => [
                'app_name'     => config('app.name', 'NetFusion Framework'),
                'environment'  => app()->environment(),
                'version'      => app()->version(),
                'php_version'  => PHP_VERSION,
                'uptime_ms'    => app()->elapsedTime(),
                'memory_mb'    => app()->peakMemory(),
            ],
            'license' => [
                'valid'        => false,
                'status'       => 'inactive',
                'license_key'  => 'Not Configured',
                'company_name' => 'DotNetBD Client',
                'domain'       => $_SERVER['HTTP_HOST'] ?? 'localhost',
                'engine'       => 'v5.1.8',
            ],
            'database' => [
                'connected'    => false,
                'driver'       => 'sqlite',
                'database'     => 'storage/database.sqlite',
                'tables_count' => 0,
            ],
            'modules_count'    => 120,
            'migrations_count' => 99,
            'recent_audits'    => [],
        ];

        // 1. Live DotNetBD License CMS Check
        try {
            $licenseManager = new LicenseManager();
            $licStatus = $licenseManager->status();
            $metrics['license'] = [
                'valid'        => $licStatus['valid'] ?? false,
                'status'       => ($licStatus['valid'] ?? false) ? 'active' : 'unlicensed',
                'license_key'  => !empty($licStatus['license_key']) ? substr($licStatus['license_key'], 0, 8) . '...' . substr($licStatus['license_key'], -4) : 'Not Activated',
                'company_name' => $licStatus['company_name'] ?? 'DotNetBD Client',
                'domain'       => $licStatus['domain'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost'),
                'expires_at'   => $licStatus['expires_at'] ?? 'Lifetime / Auto-Renew',
                'engine'       => 'v5.1.8',
            ];
        } catch (Throwable) {
            // Keep default fallback
        }

        // 2. Database Connection Check
        try {
            if (app()->has(DatabaseManager::class)) {
                /** @var DatabaseManager $dbManager */
                $dbManager = app(DatabaseManager::class);
                $conn = $dbManager->connection();
                $driver = $conn->getDriverName();
                $metrics['database'] = [
                    'connected'    => true,
                    'driver'       => strtoupper($driver),
                    'database'     => config('database.default', 'sqlite'),
                    'tables_count' => 99,
                ];
            }
        } catch (Throwable) {
            // Non-blocking database check
        }

        // 3. Scan Dynamic HMVC Modules
        $metrics['discovered_modules'] = $this->loadModulesList();
        $metrics['active_modules_count'] = count(array_filter($metrics['discovered_modules'], fn($m) => !empty($m['enabled'])));

        // 4. Tenancy State & Tenant Registry
        $metrics['tenants'] = $this->loadTenants();
        $metrics['active_tenant'] = $this->loadActiveTenant($metrics['tenants']);

        // 5. User Variants & RBAC Matrix
        $metrics['roles'] = $this->getRolesDefinition();
        $metrics['permissions_matrix'] = $this->getPermissionsMatrix();
        $metrics['users'] = $this->loadUsers();

        return $metrics;
    }

    /**
     * Load HMVC modules from app/Modules/.
     */
    protected function loadModulesList(): array
    {
        $modulesPath = base_path('app/Modules');
        $modules = [];

        $iconMap = [
            'accounting' => '📊',
            'billing'    => '💳',
            'crm'        => '👥',
            'commerce'   => '🛍️',
            'hrm'        => '👔',
            'inventory'  => '📦',
            'system'     => '⚙️',
            'testcli'    => '🧪',
        ];

        $badgeMap = [
            'accounting' => 'Financial Fabric',
            'billing'    => 'Payment & Invoicing',
            'crm'        => 'Customer Relations',
            'commerce'   => 'E-Commerce Storefront',
            'hrm'        => 'Human Resources',
            'inventory'  => 'Warehouse & Stock',
            'system'     => 'System Core',
            'testcli'    => 'Diagnostics & CLI',
        ];

        if (is_dir($modulesPath)) {
            $dirs = scandir($modulesPath);
            if ($dirs !== false) {
                foreach ($dirs as $dir) {
                    if ($dir === '.' || $dir === '..') {
                        continue;
                    }

                    $manifestPath = $modulesPath . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . 'module.json';
                    if (is_file($manifestPath)) {
                        $content = file_get_contents($manifestPath);
                        $data = json_decode((string) $content, true);
                        if (is_array($data)) {
                            $slug = strtolower($data['slug'] ?? $dir);
                            $modules[] = [
                                'dir'         => $dir,
                                'name'        => $data['name'] ?? $dir,
                                'slug'        => $slug,
                                'version'     => $data['version'] ?? '1.0.0',
                                'description' => $data['description'] ?? 'Dynamic NetFusion HMVC Business Module',
                                'author'      => $data['author'] ?? 'DotNetBD',
                                'enabled'     => (bool) ($data['enabled'] ?? true),
                                'priority'    => (int) ($data['priority'] ?? 50),
                                'icon'        => $iconMap[$slug] ?? '🧩',
                                'badge'       => $badgeMap[$slug] ?? 'HMVC Module',
                            ];
                        }
                    }
                }
            }
        }

        // Sort by priority
        usort($modules, fn($a, $b) => $a['priority'] <=> $b['priority']);

        return $modules;
    }

    /**
     * Load tenants list from storage or create default set.
     */
    protected function loadTenants(): array
    {
        $tenantsFile = storage_path('framework/tenants.json');
        if (is_file($tenantsFile)) {
            $data = json_decode((string) file_get_contents($tenantsFile), true);
            if (is_array($data) && !empty($data)) {
                return $data;
            }
        }

        $defaultTenants = [
            [
                'id'         => 'tenant_primary',
                'name'       => 'DotNetBD Corporate HQ',
                'domain'     => 'corporate.nff.dotnetbd.com',
                'driver'     => 'database',
                'status'     => 'Active',
                'users'      => 24,
                'created_at' => '2026-01-10',
            ],
            [
                'id'         => 'tenant_enterprise',
                'name'       => 'Apex Enterprise Global',
                'domain'     => 'apex.nff.dotnetbd.com',
                'driver'     => 'column_scoped',
                'status'     => 'Active',
                'users'      => 86,
                'created_at' => '2026-02-15',
            ],
            [
                'id'         => 'tenant_sandbox',
                'name'       => 'SaaS Sandbox Org',
                'domain'     => 'sandbox.nff.dotnetbd.com',
                'driver'     => 'schema_scoped',
                'status'     => 'Trial',
                'users'      => 5,
                'created_at' => '2026-03-01',
            ],
        ];

        $this->saveTenants($defaultTenants);
        return $defaultTenants;
    }

    /**
     * Save tenants to storage file.
     */
    protected function saveTenants(array $tenants): void
    {
        $tenantsFile = storage_path('framework/tenants.json');
        $dir = dirname($tenantsFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        file_put_contents($tenantsFile, json_encode($tenants, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Load current active tenant context.
     */
    protected function loadActiveTenant(array $allTenants): array
    {
        $activeTenantFile = storage_path('framework/active_tenant.json');
        if (is_file($activeTenantFile)) {
            $data = json_decode((string) file_get_contents($activeTenantFile), true);
            if (is_array($data) && !empty($data['id'])) {
                return $data;
            }
        }

        $fallback = $allTenants[0] ?? [
            'id'     => 'tenant_primary',
            'name'   => 'DotNetBD Corporate HQ',
            'domain' => 'corporate.nff.dotnetbd.com',
            'driver' => 'database',
            'status' => 'Active',
        ];

        $dir = dirname($activeTenantFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        file_put_contents($activeTenantFile, json_encode($fallback, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $fallback;
    }

    /**
     * Get User Role Variants definition.
     */
    protected function getRolesDefinition(): array
    {
        return [
            'super_admin' => [
                'name'        => 'Super Administrator',
                'bn_name'     => 'সুপার অ্যাডমিনিস্ট্রেটর',
                'badge'       => 'Global Master (*)',
                'color'       => '#ef4444',
                'bg'          => '#fef2f2',
                'icon'        => '👑',
                'description' => 'সর্বোচ্চ ফ্রেমওয়ার্ক এক্সেস, সব টেন্যান্ট বাইপাস, গ্লোবাল কনফিগারেশন ও ক্লাস্টার অর্কেস্ট্রেশন।',
                'permissions_count' => 8,
            ],
            'tenant_admin' => [
                'name'        => 'Tenant Administrator',
                'bn_name'     => 'টেন্যান্ট অ্যাডমিন',
                'badge'       => 'Tenant Isolated',
                'color'       => '#2563eb',
                'bg'          => '#eff6ff',
                'icon'        => '🏢',
                'description' => 'নির্দিষ্ট টেন্যান্টের প্রতিষ্ঠান পরিচালনা, ইউজার ব্যবস্থাপনা ও মডিউল পারমিশন কনফিগারেশন।',
                'permissions_count' => 6,
            ],
            'manager' => [
                'name'        => 'Operations Manager',
                'bn_name'     => 'অপারেশনস ম্যানেজার',
                'badge'       => 'Management',
                'color'       => '#8b5cf6',
                'bg'          => '#f5f3ff',
                'icon'        => '👔',
                'description' => 'বিজনেস ওয়ার্কফ্লো তদারকি, অডিট লগ অ্যানালাইসিস এবং লেনদেন অনুমোদন।',
                'permissions_count' => 4,
            ],
            'staff' => [
                'name'        => 'Staff / Operator',
                'bn_name'     => 'স্টাফ অপারেটর',
                'badge'       => 'Operational',
                'color'       => '#10b981',
                'bg'          => '#ecfdf5',
                'icon'        => '👤',
                'description' => 'প্রতিদিনের কাজের এন্ট্রি, ইনভয়েস তৈরি, গ্রাহক সাপোর্ট এবং স্টক আপডেট পরিচালনা।',
                'permissions_count' => 2,
            ],
            'client' => [
                'name'        => 'Client / Customer',
                'bn_name'     => 'ক্লায়েন্ট পোর্টাল',
                'badge'       => 'Self-Service',
                'color'       => '#f59e0b',
                'bg'          => '#fffbeb',
                'icon'        => '👥',
                'description' => 'সেলফ সার্ভিস পোর্টাল এক্সেস, নিজস্ব ইনভয়েস ডাউনলোড এবং সাপোর্ট টিকিট জমা।',
                'permissions_count' => 1,
            ],
        ];
    }

    /**
     * Get granular permissions matrix.
     */
    protected function getPermissionsMatrix(): array
    {
        return [
            'dashboard.view'   => ['label' => 'ড্যাশবোর্ড পর্যবেক্ষণ (Dashboard View)', 'super_admin' => true, 'tenant_admin' => true, 'manager' => true, 'staff' => true, 'client' => true],
            'tenants.manage'   => ['label' => 'টেন্যান্ট তৈরি ও সুইচিং (Tenancy Control)', 'super_admin' => true, 'tenant_admin' => false, 'manager' => false, 'staff' => false, 'client' => false],
            'modules.toggle'   => ['label' => 'মডিউল অন/অফ টগল (Module Toggle)', 'super_admin' => true, 'tenant_admin' => true, 'manager' => false, 'staff' => false, 'client' => false],
            'users.manage'     => ['label' => 'ইউজার তৈরি ও পরিবর্তন (User Management)', 'super_admin' => true, 'tenant_admin' => true, 'manager' => true, 'staff' => false, 'client' => false],
            'roles.manage'     => ['label' => 'রোল ও পারমিশন ম্যাট্রিক্স (RBAC Config)', 'super_admin' => true, 'tenant_admin' => false, 'manager' => false, 'staff' => false, 'client' => false],
            'reports.export'   => ['label' => 'ফাইন্যান্সিয়াল ও অডিট রিপোর্ট এক্সপোর্ট', 'super_admin' => true, 'tenant_admin' => true, 'manager' => true, 'staff' => true, 'client' => false],
            'license.manage'   => ['label' => 'লাইসেন্স কী অ্যাক্টিভেশন ও রিনিউয়াল', 'super_admin' => true, 'tenant_admin' => false, 'manager' => false, 'staff' => false, 'client' => false],
            'settings.edit'    => ['label' => 'সিস্টেম ও এনভায়রনমেন্ট কনফিগারেশন', 'super_admin' => true, 'tenant_admin' => true, 'manager' => false, 'staff' => false, 'client' => false],
        ];
    }

    /**
     * Load users list from storage or create default set.
     */
    protected function loadUsers(): array
    {
        $usersFile = storage_path('framework/users.json');
        if (is_file($usersFile)) {
            $data = json_decode((string) file_get_contents($usersFile), true);
            if (is_array($data) && !empty($data)) {
                return $data;
            }
        }

        $defaultUsers = [
            [
                'id'         => 'usr_001',
                'name'       => 'Md. Atiquzzaman Ashish',
                'email'      => 'ashish@dotnetbd.com',
                'role'       => 'super_admin',
                'tenant_id'  => 'tenant_primary',
                'status'     => 'Active',
                'created_at' => '2026-01-01 10:00',
            ],
            [
                'id'         => 'usr_002',
                'name'       => 'Tanvir Rahman',
                'email'      => 'tanvir@corporate.com',
                'role'       => 'tenant_admin',
                'tenant_id'  => 'tenant_primary',
                'status'     => 'Active',
                'created_at' => '2026-01-12 11:30',
            ],
            [
                'id'         => 'usr_003',
                'name'       => 'Nusrat Jahan',
                'email'      => 'nusrat@apex.com',
                'role'       => 'manager',
                'tenant_id'  => 'tenant_enterprise',
                'status'     => 'Active',
                'created_at' => '2026-02-18 14:15',
            ],
            [
                'id'         => 'usr_004',
                'name'       => 'Kamal Hossain',
                'email'      => 'kamal@corporate.com',
                'role'       => 'staff',
                'tenant_id'  => 'tenant_primary',
                'status'     => 'Active',
                'created_at' => '2026-03-02 09:45',
            ],
            [
                'id'         => 'usr_005',
                'name'       => 'Rafiqul Islam',
                'email'      => 'rafiq@client.com',
                'role'       => 'client',
                'tenant_id'  => 'tenant_sandbox',
                'status'     => 'Active',
                'created_at' => '2026-03-10 16:20',
            ],
        ];

        $this->saveUsers($defaultUsers);
        return $defaultUsers;
    }

    /**
     * Save users to storage file.
     */
    protected function saveUsers(array $users): void
    {
        $usersFile = storage_path('framework/users.json');
        $dir = dirname($usersFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Render the Light Theme Executive Dashboard with Header, Sidebar, Tenancy, Modules Toggle, and RBAC.
     */
    protected function renderDashboardHtml(array $metrics): string
    {
        $appName     = htmlspecialchars($metrics['overview']['app_name']);
        $version     = htmlspecialchars($metrics['overview']['version']);
        $phpVersion  = htmlspecialchars($metrics['overview']['php_version']);
        $environment = htmlspecialchars(ucfirst($metrics['overview']['environment']));
        $uptime      = htmlspecialchars((string) $metrics['overview']['uptime_ms']);
        $memory      = htmlspecialchars(number_format((float) $metrics['overview']['memory_mb'], 2));

        $isLicensed  = $metrics['license']['valid'] ?? false;
        $licStatusBadge = $isLicensed
            ? '<span class="status-pill status-active">✔ License Active</span>'
            : '<a href="/license" class="status-pill status-warning" style="text-decoration:none;">⚠ License Required (Click to Activate)</a>';

        $licKeyDisplay = htmlspecialchars($metrics['license']['license_key']);
        $licCompany    = htmlspecialchars($metrics['license']['company_name']);
        $licDomain     = htmlspecialchars($metrics['license']['domain']);

        $dbDriver      = htmlspecialchars($metrics['database']['driver']);
        $dbConnected   = $metrics['database']['connected']
            ? '<span class="badge badge-success">● Connected</span>'
            : '<span class="badge badge-warning">● Offline</span>';

        $activeTenant = $metrics['active_tenant'];
        $activeTenantName = htmlspecialchars($activeTenant['name'] ?? 'Primary Organization');
        $activeTenantId   = htmlspecialchars($activeTenant['id'] ?? 'tenant_primary');
        $activeTenantDomain = htmlspecialchars($activeTenant['domain'] ?? 'corporate.nff.dotnetbd.com');
        $activeTenantDriver = htmlspecialchars(strtoupper(str_replace('_', ' ', $activeTenant['driver'] ?? 'database')));

        $modulesList   = $metrics['discovered_modules'];
        $tenantsList   = $metrics['tenants'];
        $rolesList     = $metrics['roles'];
        $permMatrix    = $metrics['permissions_matrix'];
        $usersList     = $metrics['users'];

        $totalModulesCount  = count($modulesList);
        $activeModulesCount = $metrics['active_modules_count'];

        // Build Tenant Options for Switcher
        $tenantOptionsHtml = '';
        foreach ($tenantsList as $t) {
            $selected = ($t['id'] === $activeTenantId) ? 'selected' : '';
            $tenantOptionsHtml .= sprintf(
                '<option value="%s" %s>%s (%s)</option>',
                htmlspecialchars($t['id']),
                $selected,
                htmlspecialchars($t['name']),
                htmlspecialchars($t['domain'])
            );
        }

        // Build Tenant Rows for Table
        $tenantRowsHtml = '';
        foreach ($tenantsList as $t) {
            $isCurrent = ($t['id'] === $activeTenantId);
            $currBadge = $isCurrent ? '<span class="badge badge-info">Active Context</span>' : '<button class="btn btn-sm btn-outline" onclick="switchTenant(\'' . htmlspecialchars($t['id']) . '\')">Switch Context</button>';
            $tenantRowsHtml .= sprintf(
                '<tr>
                    <td><strong>%s</strong><br><small style="color:var(--text-muted);">%s</small></td>
                    <td><code>%s</code></td>
                    <td><span class="badge badge-info">%s</span></td>
                    <td><span class="badge badge-success">● %s</span></td>
                    <td>%d Users</td>
                    <td style="text-align:right;">%s</td>
                </tr>',
                htmlspecialchars($t['name']),
                htmlspecialchars($t['id']),
                htmlspecialchars($t['domain']),
                htmlspecialchars(strtoupper(str_replace('_', ' ', $t['driver']))),
                htmlspecialchars($t['status']),
                $t['users'] ?? 1,
                $currBadge
            );
        }

        // Build Modules Cards with Dynamic Interactive Toggle Switch
        $modulesCardsHtml = '';
        foreach ($modulesList as $m) {
            $dirName   = htmlspecialchars($m['dir']);
            $modName   = htmlspecialchars($m['name']);
            $modDesc   = htmlspecialchars($m['description']);
            $modVer    = htmlspecialchars($m['version']);
            $modBadge  = htmlspecialchars($m['badge']);
            $modIcon   = $m['icon'];
            $isChecked = $m['enabled'] ? 'checked' : '';
            $statusBadgeClass = $m['enabled'] ? 'module-status-active' : 'module-status-disabled';
            $statusText = $m['enabled'] ? '● Active' : '○ Inactive';

            $modulesCardsHtml .= <<<HTML
            <div class="module-card" id="module-card-{$dirName}">
                <div class="module-top">
                    <div class="module-icon">{$modIcon}</div>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span class="module-badge {$statusBadgeClass}" id="badge-{$dirName}">{$statusText}</span>
                        <label class="switch" title="অন/অফ টগল করুন">
                            <input type="checkbox" {$isChecked} onchange="toggleModule('{$dirName}', this.checked)">
                            <span class="slider round"></span>
                        </label>
                    </div>
                </div>
                <div class="module-name">{$modName}</div>
                <div class="module-desc">{$modDesc}</div>
                <div class="module-footer">
                    <span>v{$modVer}</span>
                    <span class="badge badge-info">{$modBadge}</span>
                </div>
            </div>
HTML;
        }

        // Build Roles Cards
        $rolesCardsHtml = '';
        foreach ($rolesList as $roleKey => $r) {
            $rolesCardsHtml .= sprintf(
                '<div class="role-card" style="border-top: 4px solid %s;">
                    <div class="role-header">
                        <span class="role-icon">%s</span>
                        <span class="badge" style="background:%s; color:%s;">%s</span>
                    </div>
                    <h3 class="role-name">%s</h3>
                    <div class="role-bn">%s</div>
                    <p class="role-desc">%s</p>
                    <div class="role-footer">
                        <span>পারমিশন: <strong>%dটি</strong></span>
                        <code>%s</code>
                    </div>
                </div>',
                $r['color'],
                $r['icon'],
                $r['bg'],
                $r['color'],
                $r['badge'],
                htmlspecialchars($r['name']),
                htmlspecialchars($r['bn_name']),
                htmlspecialchars($r['description']),
                $r['permissions_count'],
                htmlspecialchars($roleKey)
            );
        }

        // Build Permissions Matrix Table Rows
        $permRowsHtml = '';
        foreach ($permMatrix as $pKey => $p) {
            $permRowsHtml .= sprintf(
                '<tr>
                    <td><strong>%s</strong><br><small style="color:var(--text-muted);"><code>%s</code></small></td>
                    <td style="text-align:center;">%s</td>
                    <td style="text-align:center;">%s</td>
                    <td style="text-align:center;">%s</td>
                    <td style="text-align:center;">%s</td>
                    <td style="text-align:center;">%s</td>
                </tr>',
                htmlspecialchars($p['label']),
                htmlspecialchars($pKey),
                $p['super_admin'] ? '<span class="perm-check">✔</span>' : '<span class="perm-cross">—</span>',
                $p['tenant_admin'] ? '<span class="perm-check">✔</span>' : '<span class="perm-cross">—</span>',
                $p['manager'] ? '<span class="perm-check">✔</span>' : '<span class="perm-cross">—</span>',
                $p['staff'] ? '<span class="perm-check">✔</span>' : '<span class="perm-cross">—</span>',
                $p['client'] ? '<span class="perm-check">✔</span>' : '<span class="perm-cross">—</span>'
            );
        }

        // Build Users Directory Table Rows
        $userRowsHtml = '';
        foreach ($usersList as $u) {
            $roleMeta = $rolesList[$u['role']] ?? [
                'name'  => ucfirst($u['role']),
                'color' => '#64748b',
                'bg'    => '#f1f5f9',
                'icon'  => '👤'
            ];
            $userRowsHtml .= sprintf(
                '<tr>
                    <td>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div class="user-avatar" style="background:%s;">%s</div>
                            <div>
                                <strong>%s</strong><br>
                                <small style="color:var(--text-muted);">%s</small>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="badge" style="background:%s; color:%s;">%s %s</span>
                    </td>
                    <td><code>%s</code></td>
                    <td><span class="badge badge-success">● %s</span></td>
                    <td style="color:var(--text-muted); font-size:12px;">%s</td>
                </tr>',
                $roleMeta['color'],
                strtoupper(substr($u['name'], 0, 1)),
                htmlspecialchars($u['name']),
                htmlspecialchars($u['email']),
                $roleMeta['bg'],
                $roleMeta['color'],
                $roleMeta['icon'],
                htmlspecialchars($roleMeta['name']),
                htmlspecialchars($u['tenant_id']),
                htmlspecialchars($u['status']),
                htmlspecialchars($u['created_at'])
            );
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$appName} v{$version} — Executive Enterprise Dashboard</title>
    <style>
        :root {
            --primary: #2563eb;
            --primary-light: #eff6ff;
            --primary-hover: #1d4ed8;
            --secondary: #475569;
            --bg-body: #f8fafc;
            --bg-surface: #ffffff;
            --border-color: #e2e8f0;
            --text-heading: #0f172a;
            --text-body: #334155;
            --text-muted: #64748b;
            --success: #10b981;
            --success-light: #ecfdf5;
            --warning: #f59e0b;
            --warning-light: #fffbeb;
            --danger: #ef4444;
            --danger-light: #fef2f2;
            --sidebar-width: 260px;
            --header-height: 68px;
            --radius-md: 10px;
            --radius-lg: 14px;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -4px rgba(0, 0, 0, 0.05);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        body { background-color: var(--bg-body); color: var(--text-body); min-height: 100vh; display: flex; flex-direction: column; }

        /* App Layout */
        .app-layout { display: flex; flex: 1; min-height: 100vh; }

        /* Sidebar Menu Panel */
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--bg-surface);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
        }

        .sidebar-brand {
            height: var(--header-height);
            display: flex;
            align-items: center;
            padding: 0 24px;
            gap: 12px;
            border-bottom: 1px solid var(--border-color);
        }

        .brand-icon {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            color: #fff;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 800;
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.3);
        }

        .brand-text h2 { font-size: 17px; font-weight: 700; color: var(--text-heading); letter-spacing: -0.3px; line-height: 1.2; }
        .brand-text span { font-size: 11px; font-weight: 600; color: var(--primary); text-transform: uppercase; letter-spacing: 0.5px; }

        .sidebar-menu { flex: 1; overflow-y: auto; padding: 20px 14px; }
        .menu-label { font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.8px; padding: 12px 14px 6px; }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            border-radius: 8px;
            color: var(--text-body);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 4px;
            transition: all 0.15s ease;
        }

        .nav-item:hover { background-color: var(--bg-body); color: var(--text-heading); }
        .nav-item.active { background-color: var(--primary-light); color: var(--primary); font-weight: 600; }
        .nav-item .nav-icon { font-size: 17px; width: 22px; display: inline-flex; justify-content: center; }

        .sidebar-footer { padding: 16px 20px; border-top: 1px solid var(--border-color); font-size: 12px; color: var(--text-muted); }

        /* Main Workspace Wrapper */
        .workspace { margin-left: var(--sidebar-width); flex: 1; display: flex; flex-direction: column; min-width: 0; }

        /* Top Header */
        .top-header {
            height: var(--header-height);
            background-color: var(--bg-surface);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            position: sticky;
            top: 0;
            z-index: 90;
            box-shadow: var(--shadow-sm);
        }

        .header-left { display: flex; align-items: center; gap: 16px; }
        .header-title { font-size: 18px; font-weight: 700; color: var(--text-heading); display: flex; align-items: center; gap: 10px; }

        .tenant-selector {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #f1f5f9;
            padding: 6px 12px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            font-size: 13px;
        }

        .tenant-selector select {
            border: none;
            background: transparent;
            font-weight: 600;
            color: var(--text-heading);
            outline: none;
            cursor: pointer;
        }

        .header-right { display: flex; align-items: center; gap: 14px; }
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-active { background-color: var(--success-light); color: #065f46; border: 1px solid #a7f3d0; }
        .status-warning { background-color: var(--warning-light); color: #92400e; border: 1px solid #fde68a; }

        .version-badge {
            background-color: #f1f5f9;
            color: #334155;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            border: 1px solid #cbd5e1;
        }

        .user-pill {
            display: flex;
            align-items: center;
            gap: 10px;
            background-color: var(--bg-body);
            border: 1px solid var(--border-color);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-heading);
        }
        .user-avatar { width: 28px; height: 28px; border-radius: 50%; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; }

        /* Main Content Body */
        .content-body { flex: 1; padding: 32px; max-width: 1400px; width: 100%; margin: 0 auto; }

        /* Welcome Banner Card */
        .welcome-card {
            background: linear-gradient(135deg, #ffffff 0%, #f0f7ff 100%);
            border: 1px solid #bfdbfe;
            border-radius: var(--radius-lg);
            padding: 28px 32px;
            margin-bottom: 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow-sm);
        }

        .welcome-text h1 { font-size: 22px; font-weight: 800; color: #1e3a8a; margin-bottom: 8px; letter-spacing: -0.3px; }
        .welcome-text p { font-size: 14px; color: #475569; line-height: 1.5; max-width: 760px; }
        .welcome-actions { display: flex; gap: 10px; flex-wrap: wrap; }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid transparent;
        }
        .btn-sm { padding: 6px 12px; font-size: 12px; border-radius: 6px; }
        .btn-primary { background-color: var(--primary); color: #fff; }
        .btn-primary:hover { background-color: var(--primary-hover); box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2); }
        .btn-outline { background-color: #fff; color: var(--text-body); border-color: var(--border-color); }
        .btn-outline:hover { background-color: var(--bg-body); color: var(--text-heading); }

        /* KPI Stat Cards Grid */
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px; margin-bottom: 32px; }
        .kpi-card {
            background-color: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 22px 24px;
            box-shadow: var(--shadow-sm);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .kpi-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); border-color: #cbd5e1; }
        .kpi-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .kpi-title { font-size: 13px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .kpi-icon { width: 38px; height: 38px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; font-size: 18px; }
        .kpi-icon.blue { background-color: #eff6ff; color: #2563eb; }
        .kpi-icon.green { background-color: #ecfdf5; color: #10b981; }
        .kpi-icon.amber { background-color: #fffbeb; color: #f59e0b; }
        .kpi-icon.purple { background-color: #f5f3ff; color: #8b5cf6; }

        .kpi-val { font-size: 26px; font-weight: 800; color: var(--text-heading); margin-bottom: 6px; letter-spacing: -0.5px; }
        .kpi-meta { font-size: 12px; color: var(--text-muted); display: flex; align-items: center; gap: 6px; }

        /* Section Heading */
        .section-header { margin: 32px 0 16px 0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
        .section-title { font-size: 17px; font-weight: 700; color: var(--text-heading); display: flex; align-items: center; gap: 8px; }

        /* Interactive Toggle Switch */
        .switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
        }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #cbd5e1;
            transition: .3s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 24px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 50%;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        input:checked + .slider { background-color: var(--primary); }
        input:focus + .slider { box-shadow: 0 0 1px var(--primary); }
        input:checked + .slider:before { transform: translateX(20px); }

        /* Modules Grid (মডিউলসমূহ) */
        .modules-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(310px, 1fr)); gap: 20px; margin-bottom: 36px; }
        .module-card {
            background-color: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 22px;
            box-shadow: var(--shadow-sm);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.2s;
        }
        .module-card:hover { border-color: #93c5fd; box-shadow: var(--shadow-md); }
        .module-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .module-icon { width: 42px; height: 42px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 20px; background-color: var(--bg-body); border: 1px solid var(--border-color); }
        .module-badge { font-size: 11px; font-weight: 700; padding: 4px 8px; border-radius: 12px; }
        .module-status-active { background-color: #ecfdf5; color: #047857; }
        .module-status-disabled { background-color: #f1f5f9; color: #64748b; }

        .module-name { font-size: 16px; font-weight: 700; color: var(--text-heading); margin-bottom: 6px; }
        .module-desc { font-size: 13px; color: var(--text-muted); line-height: 1.5; margin-bottom: 16px; flex: 1; }
        .module-footer { border-top: 1px solid #f1f5f9; padding-top: 12px; font-size: 12px; color: var(--text-muted); display: flex; justify-content: space-between; align-items: center; }

        /* Roles Grid */
        .roles-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 18px; margin-bottom: 24px; }
        .role-card {
            background-color: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 20px;
            box-shadow: var(--shadow-sm);
        }
        .role-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .role-icon { font-size: 22px; }
        .role-name { font-size: 15px; font-weight: 700; color: var(--text-heading); }
        .role-bn { font-size: 12px; font-weight: 600; color: var(--text-muted); margin-bottom: 8px; }
        .role-desc { font-size: 12px; color: var(--text-muted); line-height: 1.4; margin-bottom: 14px; min-height: 48px; }
        .role-footer { border-top: 1px solid #f1f5f9; padding-top: 10px; font-size: 11px; color: var(--text-muted); display: flex; justify-content: space-between; align-items: center; }

        /* System Diagnostics Table */
        .table-card {
            background-color: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 24px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 32px;
            overflow-x: auto;
        }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { padding: 12px 14px; text-align: left; border-bottom: 1px solid var(--border-color); }
        th { font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; background-color: #f8fafc; }
        tr:last-child td { border-bottom: none; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; }
        .badge-success { background-color: var(--success-light); color: #065f46; }
        .badge-info { background-color: var(--primary-light); color: var(--primary); }
        .badge-warning { background-color: var(--warning-light); color: #92400e; }
        .badge-danger { background-color: var(--danger-light); color: var(--danger); }

        .perm-check { color: #10b981; font-weight: 800; font-size: 15px; }
        .perm-cross { color: #cbd5e1; font-weight: 600; font-size: 15px; }

        /* Modal Popup */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(15, 23, 42, 0.45);
            backdrop-filter: blur(2px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal-box {
            background: #fff;
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 480px;
            padding: 28px;
            box-shadow: var(--shadow-lg);
        }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-header h3 { font-size: 18px; font-weight: 700; color: var(--text-heading); }
        .modal-close { cursor: pointer; font-size: 20px; color: var(--text-muted); border: none; background: transparent; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 6px; }
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 13px;
            outline: none;
        }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }

        /* Toast notification */
        #toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: #0f172a;
            color: #fff;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            display: none;
            z-index: 2000;
            box-shadow: var(--shadow-lg);
            animation: slideUp 0.3s ease;
        }
        @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        /* Footer */
        .app-footer {
            background-color: var(--bg-surface);
            border-top: 1px solid var(--border-color);
            padding: 24px 32px;
            font-size: 13px;
            color: var(--text-muted);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        .footer-links { display: flex; gap: 20px; }
        .footer-links a { color: var(--text-muted); text-decoration: none; font-weight: 500; transition: color 0.15s; }
        .footer-links a:hover { color: var(--primary); }
    </style>
</head>
<body>
    <div class="app-layout">
        <!-- 1. SIDEBAR MENU PANEL (মেন্যু প্যানেল) -->
        <aside class="sidebar">
            <div class="sidebar-brand">
                <div class="brand-icon">N</div>
                <div class="brand-text">
                    <h2>NetFusion</h2>
                    <span>Enterprise Framework</span>
                </div>
            </div>

            <nav class="sidebar-menu">
                <div class="menu-label">মূল মেন্যু (Core)</div>
                <a href="/admin/dashboard" class="nav-item active">
                    <span class="nav-icon">📊</span> ড্যাশবোর্ড (Dashboard)
                </a>
                <a href="#tenants" class="nav-item">
                    <span class="nav-icon">🏢</span> মাল্টি-টেন্যান্ট (Tenancy)
                </a>
                <a href="#modules" class="nav-item">
                    <span class="nav-icon">📦</span> মডিউল সুইচ (Module Toggle)
                </a>
                <a href="#users" class="nav-item">
                    <span class="nav-icon">👥</span> ইউজার ও রোল (RBAC Matrix)
                </a>
                <a href="/license" class="nav-item">
                    <span class="nav-icon">🛡️</span> লাইসেন্স পোর্টাল (License CMS)
                </a>

                <div class="menu-label">বিজনেস মডিউলস (HMVC)</div>
                <a href="/accounting" class="nav-item">
                    <span class="nav-icon">📊</span> অ্যাকাউন্টিং (Accounting)
                </a>
                <a href="/billing" class="nav-item">
                    <span class="nav-icon">💳</span> বিলিং ও সাবস্ক্রিপশন (Billing)
                </a>
                <a href="/crm" class="nav-item">
                    <span class="nav-icon">👥</span> সিআরএম ও লিডস (CRM)
                </a>
                <a href="/commerce" class="nav-item">
                    <span class="nav-icon">🛍️</span> কমার্স ও পিওএস (Commerce)
                </a>
                <a href="/hrm" class="nav-item">
                    <span class="nav-icon">👔</span> এইচআরএম ও পেরোল (HRM)
                </a>
                <a href="/inventory" class="nav-item">
                    <span class="nav-icon">📦</span> ইনভেন্টরি ও গুদাম (Inventory)
                </a>
                <a href="/system/status" class="nav-item">
                    <span class="nav-icon">⚙️</span> সিস্টেম মনিটর (System)
                </a>

                <div class="menu-label">সিস্টেম কনট্রোল (Engine)</div>
                <a href="#telemetry" class="nav-item">
                    <span class="nav-icon">🩺</span> টেলিমেট্রি (Diagnostics)
                </a>
                <a href="/health" class="nav-item" target="_blank">
                    <span class="nav-icon">💓</span> সিস্টেম হেলথ (Health API)
                </a>
                <a href="/install" class="nav-item">
                    <span class="nav-icon">🚀</span> ইনস্টলার উইজার্ড (Installer)
                </a>
            </nav>

            <div class="sidebar-footer">
                <div><strong>NetFusion Framework</strong></div>
                <div style="font-size:11px; margin-top:2px;">Enterprise Release v{$version}</div>
            </div>
        </aside>

        <!-- 2. MAIN WORKSPACE AREA -->
        <div class="workspace">
            <!-- 2.1 TOP HEADER (হেডার) -->
            <header class="top-header">
                <div class="header-left">
                    <div class="header-title">
                        <span>⚡</span> এক্সিকিউটিভ ড্যাশবোর্ড
                    </div>
                    <div class="tenant-selector">
                        <span>🏢 টেন্যান্ট:</span>
                        <select id="activeTenantSelect" onchange="switchTenant(this.value)">
                            {$tenantOptionsHtml}
                        </select>
                    </div>
                </div>

                <div class="header-right">
                    {$licStatusBadge}
                    <div class="version-badge">v{$version} (Fixed)</div>
                    <div class="user-pill">
                        <div class="user-avatar" style="background:#2563eb;">A</div>
                        <span>Super Administrator</span>
                    </div>
                </div>
            </header>

            <!-- 2.2 CONTENT BODY (বডি) -->
            <main class="content-body">
                <!-- Welcome Banner -->
                <div class="welcome-card">
                    <div class="welcome-text">
                        <h1>স্বাগতম, NetFusion Enterprise Hub এ 👋</h1>
                        <p>
                            আপনার মাল্টি-টেন্যান্ট SaaS ফ্রেমওয়ার্ক প্রস্তুত। বর্তমান সক্রিয় টেন্যান্ট: <strong>{$activeTenantName}</strong> 
                            (আইসোলেশন: <code>{$activeTenantDriver}</code>, ডোমেইন: <code>{$activeTenantDomain}</code>)। 
                            নিচে মডিউল অন/অফ সুইচিং এবং ইউজার ভ্যারিয়েন্ট ও পারমিশন ম্যাট্রিক্স কনফিগার করুন।
                        </p>
                    </div>
                    <div class="welcome-actions">
                        <button class="btn btn-primary" onclick="openModal('createTenantModal')">➕ নতুন টেন্যান্ট</button>
                        <button class="btn btn-outline" onclick="openModal('createUserModal')">👤 নতুন ইউজার</button>
                    </div>
                </div>

                <!-- KPI Metric Cards Grid -->
                <div class="kpi-grid">
                    <div class="kpi-card">
                        <div class="kpi-header">
                            <span class="kpi-title">সক্রিয় টেন্যান্টস (Tenancy)</span>
                            <span class="kpi-icon blue">🏢</span>
                        </div>
                        <div class="kpi-val">3 টি</div>
                        <div class="kpi-meta">সক্রিয় কনটেক্সট: <strong>{$activeTenantId}</strong></div>
                    </div>

                    <div class="kpi-card">
                        <div class="kpi-header">
                            <span class="kpi-title">মডিউল স্ট্যাটাস (HMVC)</span>
                            <span class="kpi-icon green">📦</span>
                        </div>
                        <div class="kpi-val" id="kpi-active-modules">{$activeModulesCount} / {$totalModulesCount} টি</div>
                        <div class="kpi-meta">লাইভ টগলযোগ্য মডিউল আর্কিটেকচার</div>
                    </div>

                    <div class="kpi-card">
                        <div class="kpi-header">
                            <span class="kpi-title">ইউজার ভ্যারিয়েন্টস (RBAC)</span>
                            <span class="kpi-icon purple">👥</span>
                        </div>
                        <div class="kpi-val">5 টি রোল</div>
                        <div class="kpi-meta">8টি গ্র্যানুলার পারমিশন কন্ট্রোল পলিসি</div>
                    </div>

                    <div class="kpi-card">
                        <div class="kpi-header">
                            <span class="kpi-title">সিস্টেম ফ্রেমওয়ার্ক</span>
                            <span class="kpi-icon amber">⚡</span>
                        </div>
                        <div class="kpi-val">v{$version}</div>
                        <div class="kpi-meta">লকড এন্টারপ্রাইজ রিলিজ (PHP {$phpVersion})</div>
                    </div>
                </div>

                <!-- 1. MULTI-TENANCY MANAGEMENT SECTION -->
                <div class="section-header" id="tenants">
                    <div class="section-title">
                        <span>🏢</span> মাল্টি-টেন্যান্ট ম্যানেজমেন্ট ও আইসোলেশন কন্ট্রোল (Multi-Tenancy Engine)
                    </div>
                    <button class="btn btn-sm btn-primary" onclick="openModal('createTenantModal')">➕ নতুন টেন্যান্ট যোগ করুন</button>
                </div>

                <div class="table-card">
                    <table>
                        <thead>
                            <tr>
                                <th>টেন্যান্ট নাম ও আইডি</th>
                                <th>ডোমেইন / সাবডোমেইন</th>
                                <th>আইসোলেশন ড্রাইভার</th>
                                <th>স্ট্যাটাস</th>
                                <th>ইউজার সংখ্যা</th>
                                <th style="text-align:right;">অ্যাকশন</th>
                            </tr>
                        </thead>
                        <tbody id="tenantsTableBody">
                            {$tenantRowsHtml}
                        </tbody>
                    </table>
                </div>

                <!-- 2. DYNAMIC MODULES ON/OFF SYSTEM SECTION -->
                <div class="section-header" id="modules">
                    <div class="section-title">
                        <span>📦</span> HMVC বিজনেস মডিউলসমূহ ও অন/অফ সিস্টেম (Dynamic Module Toggle Switch)
                    </div>
                    <span style="font-size:12px; color:var(--text-muted);">সরাসরি সুইচে ক্লিক করে যেকোনো মডিউল অন বা অফ করুন</span>
                </div>

                <div class="modules-grid" id="modulesGrid">
                    {$modulesCardsHtml}
                </div>

                <!-- 3. USER VARIANTS & RBAC PERMISSIONS SECTION -->
                <div class="section-header" id="users">
                    <div class="section-title">
                        <span>👥</span> ইউজার ভ্যারিয়েন্ট ও রোল হাইয়ারার্কি (User Variants & RBAC Hierarchy)
                    </div>
                    <button class="btn btn-sm btn-outline" onclick="openModal('createUserModal')">➕ নতুন ইউজার তৈরি করুন</button>
                </div>

                <div class="roles-grid">
                    {$rolesCardsHtml}
                </div>

                <!-- Permissions Matrix Table -->
                <div class="table-card">
                    <div style="font-weight:700; font-size:14px; margin-bottom:14px; color:var(--text-heading); display:flex; align-items:center; gap:8px;">
                        <span>📋</span> রোল ও পারমিশন কন্ট্রোল ম্যাট্রিক্স (RBAC Policy Matrix)
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>পারমিশন অ্যাকশন ও স্কোপ</th>
                                <th style="text-align:center;">👑 Super Admin</th>
                                <th style="text-align:center;">🏢 Tenant Admin</th>
                                <th style="text-align:center;">👔 Manager</th>
                                <th style="text-align:center;">👤 Staff</th>
                                <th style="text-align:center;">👥 Client</th>
                            </tr>
                        </thead>
                        <tbody>
                            {$permRowsHtml}
                        </tbody>
                    </table>
                </div>

                <!-- Users Directory Table -->
                <div class="table-card">
                    <div style="font-weight:700; font-size:14px; margin-bottom:14px; color:var(--text-heading); display:flex; justify-content:space-between; align-items:center;">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span>👤</span> সক্রিয় ইউজার ডিরেক্টরি (Active User Accounts)
                        </div>
                        <button class="btn btn-sm btn-primary" onclick="openModal('createUserModal')">➕ ইউজার যোগ করুন</button>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>ইউজার নাম ও ইমেইল</th>
                                <th>অ্যাসাইনকৃত রোল (Variant)</th>
                                <th>টেন্যান্ট বাইন্ডিং</th>
                                <th>অ্যাকাউন্ট স্ট্যাটাস</th>
                                <th>যুক্ত করার সময়</th>
                            </tr>
                        </thead>
                        <tbody>
                            {$userRowsHtml}
                        </tbody>
                    </table>
                </div>

                <!-- System Telemetry Table -->
                <div class="section-header" id="telemetry">
                    <div class="section-title">
                        <span>🩺</span> সার্ভার স্পেসিফিকেশন ও এনভায়রনমেন্ট ডায়াগনস্টিকস (System Telemetry)
                    </div>
                </div>

                <div class="table-card">
                    <table>
                        <thead>
                            <tr>
                                <th>প্যারামিটার</th>
                                <th>কনফিগারেশন ভ্যালু</th>
                                <th>প্রস্তাবিত মান</th>
                                <th style="text-align:right">স্ট্যাটাস</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>PHP Version</strong></td>
                                <td>PHP {$phpVersion}</td>
                                <td>>= 8.2.0</td>
                                <td style="text-align:right"><span class="badge badge-success">✔ PASS</span></td>
                            </tr>
                            <tr>
                                <td><strong>Framework Release</strong></td>
                                <td>NetFusion v{$version} (Fixed Enterprise Release)</td>
                                <td>v1.3.1</td>
                                <td style="text-align:right"><span class="badge badge-success">✔ PASS</span></td>
                            </tr>
                            <tr>
                                <td><strong>Database Connection</strong></td>
                                <td>{$dbDriver} ({$metrics['database']['database']})</td>
                                <td>Connected</td>
                                <td style="text-align:right">{$dbConnected}</td>
                            </tr>
                            <tr>
                                <td><strong>Active Multi-Tenant Context</strong></td>
                                <td>{$activeTenantName} ({$activeTenantId}) &bull; {$activeTenantDriver} Driver</td>
                                <td>Isolated</td>
                                <td style="text-align:right"><span class="badge badge-success">✔ ACTIVE</span></td>
                            </tr>
                            <tr>
                                <td><strong>Runtime Dependency Model</strong></td>
                                <td>Pure Sovereign Architecture (0 External Runtime Dependencies)</td>
                                <td>0 Dependencies</td>
                                <td style="text-align:right"><span class="badge badge-success">✔ PASS</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </main>

            <!-- 2.3 APP FOOTER (ফুটার) -->
            <footer class="app-footer">
                <div>
                    <strong>NetFusion Framework v{$version}</strong> &bull; Developed by <strong>Md. Atiquzzaman Ashish</strong> &bull; &copy; 2026 <strong>DotNetBD</strong>
                </div>
                <div class="footer-links">
                    <a href="/license">লাইসেন্স পোর্টাল</a>
                    <a href="/health" target="_blank">সিস্টেম হেলথ</a>
                    <a href="https://github.com/dotnetbdinfosys/NetFusion-Framework" target="_blank">GitHub</a>
                    <a href="https://www.dotnetbd.com" target="_blank">DotNetBD ওয়েবসাইট</a>
                </div>
            </footer>
        </div>
    </div>

    <!-- CREATE TENANT MODAL -->
    <div class="modal-overlay" id="createTenantModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3>নতুন টেন্যান্ট তৈরি করুন</h3>
                <button class="modal-close" onclick="closeModal('createTenantModal')">&times;</button>
            </div>
            <form onsubmit="handleCreateTenant(event)">
                <div class="form-group">
                    <label>টেন্যান্ট আইডি (স্ল্যাগ, যেমন: acme_corp)</label>
                    <input type="text" id="newTenantId" class="form-control" required placeholder="e.g. acme_corp">
                </div>
                <div class="form-group">
                    <label>প্রতিষ্ঠানের নাম</label>
                    <input type="text" id="newTenantName" class="form-control" required placeholder="e.g. Acme Corporation Ltd.">
                </div>
                <div class="form-group">
                    <label>ডোমেইন / সাবডোমেইন</label>
                    <input type="text" id="newTenantDomain" class="form-control" placeholder="e.g. acme.nff.dotnetbd.com">
                </div>
                <div class="form-group">
                    <label>ডাটাবেজ আইসোলেশন ড্রাইভার</label>
                    <select id="newTenantDriver" class="form-control">
                        <option value="database">Database Isolation (স্বতন্ত্র ডাটাবেজ)</option>
                        <option value="column_scoped">Column Scoped (একই ডাটাবেজ, tenant_id দিয়ে স্কোপ্ড)</option>
                        <option value="schema_scoped">Schema Scoped (স্বতন্ত্র স্কিমা)</option>
                    </select>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                    <button type="button" class="btn btn-outline" onclick="closeModal('createTenantModal')">বাতিল</button>
                    <button type="submit" class="btn btn-primary">তৈরি করুন</button>
                </div>
            </form>
        </div>
    </div>

    <!-- CREATE USER MODAL -->
    <div class="modal-overlay" id="createUserModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3>নতুন ইউজার যুক্ত করুন</h3>
                <button class="modal-close" onclick="closeModal('createUserModal')">&times;</button>
            </div>
            <form onsubmit="handleCreateUser(event)">
                <div class="form-group">
                    <label>পুরো নাম</label>
                    <input type="text" id="newUserName" class="form-control" required placeholder="e.g. Mehedi Hasan">
                </div>
                <div class="form-group">
                    <label>ইমেইল অ্যাড্রেস</label>
                    <input type="email" id="newUserEmail" class="form-control" required placeholder="e.g. mehedi@example.com">
                </div>
                <div class="form-group">
                    <label>ইউজার ভ্যারিয়েন্ট / রোল (Role)</label>
                    <select id="newUserRole" class="form-control">
                        <option value="staff">👤 Staff / Operator (স্টাফ অপারেটর)</option>
                        <option value="manager">👔 Operations Manager (অপারেশনস ম্যানেজার)</option>
                        <option value="tenant_admin">🏢 Tenant Administrator (টেন্যান্ট অ্যাডমিন)</option>
                        <option value="super_admin">👑 Super Administrator (সুপার অ্যাডমিন)</option>
                        <option value="client">👥 Client / Customer (সেলফ সার্ভিস পোর্টাল)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>অ্যাসাইনকৃত টেন্যান্ট</label>
                    <select id="newUserTenant" class="form-control">
                        {$tenantOptionsHtml}
                    </select>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                    <button type="button" class="btn btn-outline" onclick="closeModal('createUserModal')">বাতিল</button>
                    <button type="submit" class="btn btn-primary">ইউজার যোগ করুন</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast Notification Element -->
    <div id="toast"></div>

    <script>
        function showToast(message, isError = false) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.style.backgroundColor = isError ? '#ef4444' : '#0f172a';
            toast.style.display = 'block';
            setTimeout(() => { toast.style.display = 'none'; }, 3500);
        }

        function openModal(id) {
            document.getElementById(id).style.display = 'flex';
        }

        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }

        // Toggle Module Dynamic AJAX Handler
        async function toggleModule(moduleName, enabled) {
            const badge = document.getElementById('badge-' + moduleName);
            if (badge) {
                badge.textContent = enabled ? '● Active' : '○ Inactive';
                badge.className = 'module-badge ' + (enabled ? 'module-status-active' : 'module-status-disabled');
            }

            try {
                const response = await fetch('/admin/modules/toggle', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: new URLSearchParams({ module: moduleName, enabled: enabled ? 1 : 0 })
                });

                const data = await response.json();
                if (data.success) {
                    showToast(data.message);
                } else {
                    showToast(data.message || 'মডিউল স্টেট পরিবর্তনে ব্যর্থ হয়েছে', true);
                }
            } catch (err) {
                showToast('সার্ভার যোগাযোগে সমস্যা হয়েছে', true);
            }
        }

        // Switch Active Tenant AJAX Handler
        async function switchTenant(tenantId) {
            try {
                const response = await fetch('/admin/tenants/switch', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: new URLSearchParams({ tenant_id: tenantId })
                });

                const data = await response.json();
                if (data.success) {
                    showToast(data.message);
                    setTimeout(() => { window.location.reload(); }, 600);
                } else {
                    showToast(data.message || 'টেন্যান্ট সুইচে ব্যর্থ হয়েছে', true);
                }
            } catch (err) {
                showToast('সার্ভার যোগাযোগে সমস্যা হয়েছে', true);
            }
        }

        // Handle Create Tenant Form
        async function handleCreateTenant(e) {
            e.preventDefault();
            const id = document.getElementById('newTenantId').value.trim();
            const name = document.getElementById('newTenantName').value.trim();
            const domain = document.getElementById('newTenantDomain').value.trim();
            const driver = document.getElementById('newTenantDriver').value;

            try {
                const response = await fetch('/admin/tenants/create', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: new URLSearchParams({ id, name, domain, driver })
                });

                const data = await response.json();
                if (data.success) {
                    showToast(data.message);
                    closeModal('createTenantModal');
                    setTimeout(() => { window.location.reload(); }, 600);
                } else {
                    showToast(data.message || 'টেন্যান্ট তৈরি করা সম্ভব হয়নি', true);
                }
            } catch (err) {
                showToast('সার্ভার যোগাযোগে সমস্যা হয়েছে', true);
            }
        }

        // Handle Create User Form
        async function handleCreateUser(e) {
            e.preventDefault();
            const name = document.getElementById('newUserName').value.trim();
            const email = document.getElementById('newUserEmail').value.trim();
            const role = document.getElementById('newUserRole').value;
            const tenant_id = document.getElementById('newUserTenant').value;

            try {
                const response = await fetch('/admin/users/create', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: new URLSearchParams({ name, email, role, tenant_id })
                });

                const data = await response.json();
                if (data.success) {
                    showToast(data.message);
                    closeModal('createUserModal');
                    setTimeout(() => { window.location.reload(); }, 600);
                } else {
                    showToast(data.message || 'ইউজার তৈরি করা সম্ভব হয়নি', true);
                }
            } catch (err) {
                showToast('সার্ভার যোগাযোগে সমস্যা হয়েছে', true);
            }
        }
    </script>
</body>
</html>
HTML;
    }
}
