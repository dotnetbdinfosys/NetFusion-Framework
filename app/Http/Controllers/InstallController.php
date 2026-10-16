<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Http/Controllers/InstallController.php
| Module    : Installation
| Namespace : App\Http\Controllers
| Purpose   : Interactive Web Setup Wizard Controller guiding zero-terminal first-run onboarding.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| Website   : www.dotnetbd.com
| Copyright : © DotNetBD. All rights reserved.
| License   : MIT
| Version   : 1.3.5
|
|--------------------------------------------------------------------------
*/

namespace NetFusion\App\Http\Controllers;

use DateTimeImmutable;
use NetFusion\Core\Database\Migrations\Migrator;
use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;
use PDO;
use Throwable;

class InstallController
{
    protected string $lockFile;

    public function __construct()
    {
        $this->lockFile = function_exists('storage_path') ? storage_path('installed') : 'storage/installed';
    }

    /**
     * Display the interactive installer wizard.
     */
    public function index(Request $request): Response
    {
        if (file_exists($this->lockFile)) {
            return Response::redirect('/admin/dashboard');
        }

        $requirements = $this->getSystemRequirements();
        $permissions  = $this->getDirectoryPermissions();

        $canProceed = true;
        foreach ($requirements as $req) {
            if (!$req['status']) {
                $canProceed = false;
                break;
            }
        }
        foreach ($permissions as $perm) {
            if (!$perm['writable']) {
                $canProceed = false;
                break;
            }
        }

        // Render installer view
        if (function_exists('view') && app()->has('view')) {
            try {
                return view('installer.install', [
                    'requirements' => $requirements,
                    'permissions'  => $permissions,
                    'canProceed'   => $canProceed,
                    'phpVersion'   => PHP_VERSION,
                    'osFamily'     => PHP_OS_FAMILY,
                ]);
            } catch (Throwable) {
                // Fallback to direct HTML if view factory encounters template lookup issue
            }
        }

        return Response::html($this->renderInstallHtml($requirements, $permissions, $canProceed));
    }

    /**
     * AJAX endpoint to test database credentials.
     */
    public function checkDatabase(Request $request): Response
    {
        $driver   = (string) $request->input('driver', 'mysql');
        $host     = (string) $request->input('host', '127.0.0.1');
        $port     = (int) ($request->input('port') ?: 3306);
        $database = (string) $request->input('database', '');
        $username = (string) $request->input('username', '');
        $password = (string) $request->input('password', '');

        try {
            if ($driver === 'sqlite') {
                $dbPath = $database ?: ':memory:';
                $pdo = new PDO("sqlite:{$dbPath}", null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
            } elseif ($driver === 'pgsql') {
                $dsn = "pgsql:host={$host};port={$port};dbname={$database}";
                $pdo = new PDO($dsn, $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
            } else {
                // MySQL / MariaDB
                $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
                $pdo = new PDO($dsn, $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
            }

            return Response::json([
                'success' => true,
                'message' => '✔ Database connection established successfully!',
            ]);
        } catch (Throwable $e) {
            return Response::json([
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Process installation: write .env, run migrations, write lock file.
     */
    public function setup(Request $request): Response
    {
        if (file_exists($this->lockFile)) {
            return Response::json(['error' => 'Application is already installed.'], 403);
        }

        $appName  = (string) $request->input('app_name', 'NetFusion Enterprise');
        $appUrl   = (string) $request->input('app_url', 'http://localhost');
        $driver   = (string) $request->input('db_driver', 'mysql');
        $host     = (string) $request->input('db_host', '127.0.0.1');
        $port     = (string) ($request->input('db_port') ?: '3306');
        $database = (string) $request->input('db_database', 'netfusion');
        $username = (string) $request->input('db_username', 'root');
        $password = (string) $request->input('db_password', '');

        $adminName  = (string) $request->input('admin_name', 'Administrator');
        $adminEmail = (string) $request->input('admin_email', 'admin@example.com');
        $adminPass  = (string) $request->input('admin_password', 'password');

        $licenseKey  = trim((string) $request->input('license_key', ''));
        $companyName = trim((string) $request->input('company_name', ''));

        try {
            // 1. Update or generate .env file
            $basePath = function_exists('base_path') ? base_path() : dirname(__DIR__, 2);
            $envPath  = $basePath . DIRECTORY_SEPARATOR . '.env';

            // Ensure SQLite database file exists if sqlite selected
            if ($driver === 'sqlite') {
                $sqliteFile = $database ?: ($basePath . '/storage/database.sqlite');
                if (!file_exists($sqliteFile)) {
                    @touch($sqliteFile);
                }
            }

            $appKey = 'base64:' . base64_encode(random_bytes(32));

            $envContent = <<<ENV
APP_NAME="{$appName}"
APP_ENV=production
APP_KEY={$appKey}
APP_DEBUG=false
APP_URL={$appUrl}

LOG_CHANNEL=daily
LOG_LEVEL=info

DB_CONNECTION={$driver}
DB_HOST={$host}
DB_PORT={$port}
DB_DATABASE={$database}
DB_USERNAME={$username}
DB_PASSWORD={$password}

CACHE_DRIVER=file
QUEUE_CONNECTION=sync
MAIL_MAILER=smtp
FILESYSTEM_DISK=public
ENV;

            file_put_contents($envPath, $envContent);

            // 2. Initialize default users table & super admin account
            try {
                if ($driver === 'sqlite') {
                    $pdo = new PDO("sqlite:{$database}", null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        name TEXT NOT NULL,
                        email TEXT NOT NULL UNIQUE,
                        password TEXT NOT NULL,
                        role TEXT DEFAULT 'admin',
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    )");
                } elseif ($driver === 'pgsql') {
                    $pdo = new PDO("pgsql:host={$host};port={$port};dbname={$database}", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
                        id SERIAL PRIMARY KEY,
                        name VARCHAR(255) NOT NULL,
                        email VARCHAR(255) NOT NULL UNIQUE,
                        password VARCHAR(255) NOT NULL,
                        role VARCHAR(50) DEFAULT 'admin',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )");
                } else {
                    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        name VARCHAR(255) NOT NULL,
                        email VARCHAR(255) NOT NULL UNIQUE,
                        password VARCHAR(255) NOT NULL,
                        role VARCHAR(50) DEFAULT 'admin',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                }

                $chk = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $chk->execute([$adminEmail]);
                if (!$chk->fetch()) {
                    $passHash = password_hash($adminPass, PASSWORD_BCRYPT);
                    $ins = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')");
                    $ins->execute([$adminName, $adminEmail, $passHash]);
                }
            } catch (Throwable) {
                // Non-blocking user creation
            }

            // 3. DotNetBD License CMS Activation (if provided)
            if (!empty($licenseKey) && !empty($companyName)) {
                try {
                    $licenseManager = new \NetFusion\Core\Foundation\License\LicenseManager();
                    $licenseManager->activate($licenseKey, $companyName, parse_url($appUrl, PHP_URL_HOST));
                } catch (Throwable) {
                    // Non-fatal license activation error
                }
            }

            // 4. Mark as installed
            $lockContent = json_encode([
                'installed_at'  => (new DateTimeImmutable())->format('c'),
                'framework'     => 'NetFusion Framework',
                'version'       => '1.3.5',
                'app_name'      => $appName,
                'app_url'       => $appUrl,
                'admin_email'   => $adminEmail,
                'author'        => 'Md. Atiquzzaman Ashish (DotNetBD)',
            ], JSON_PRETTY_PRINT);

            file_put_contents($this->lockFile, $lockContent);

            return Response::json([
                'success'  => true,
                'redirect' => '/install/completed',
            ]);
        } catch (Throwable $e) {
            return Response::json([
                'success' => false,
                'message' => 'Setup failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display installation completion view.
     */
    public function completed(Request $request): Response
    {
        return Response::html($this->renderCompletedHtml());
    }

    /**
     * Get system requirements status.
     *
     * @return array<array{name: string, required: string, current: string, status: bool}>
     */
    public function getSystemRequirements(): array
    {
        return [
            ['name' => 'PHP Version', 'required' => '>= 8.2.0', 'current' => PHP_VERSION, 'status' => version_compare(PHP_VERSION, '8.2.0', '>=')],
            ['name' => 'PDO Extension', 'required' => 'Required', 'current' => extension_loaded('pdo') ? 'Loaded' : 'Missing', 'status' => extension_loaded('pdo')],
            ['name' => 'OpenSSL Extension', 'required' => 'Required', 'current' => extension_loaded('openssl') ? 'Loaded' : 'Missing', 'status' => extension_loaded('openssl')],
            ['name' => 'Mbstring Extension', 'required' => 'Required', 'current' => extension_loaded('mbstring') ? 'Loaded' : 'Missing', 'status' => extension_loaded('mbstring')],
            ['name' => 'cURL Extension', 'required' => 'Required', 'current' => extension_loaded('curl') ? 'Loaded' : 'Missing', 'status' => extension_loaded('curl')],
            ['name' => 'JSON Extension', 'required' => 'Required', 'current' => extension_loaded('json') ? 'Loaded' : 'Missing', 'status' => extension_loaded('json')],
            ['name' => 'BCMath Extension', 'required' => 'Required', 'current' => extension_loaded('bcmath') ? 'Loaded' : 'Missing', 'status' => extension_loaded('bcmath')],
            ['name' => 'Ctype Extension', 'required' => 'Required', 'current' => extension_loaded('ctype') ? 'Loaded' : 'Missing', 'status' => extension_loaded('ctype')],
            ['name' => 'Tokenizer Extension', 'required' => 'Required', 'current' => extension_loaded('tokenizer') ? 'Loaded' : 'Missing', 'status' => extension_loaded('tokenizer')],
        ];
    }

    /**
     * Check writable directory permissions.
     *
     * @return array<array{path: string, writable: bool}>
     */
    public function getDirectoryPermissions(): array
    {
        $base = function_exists('base_path') ? base_path() : dirname(__DIR__, 2);

        $dirs = [
            'storage'                 => $base . DIRECTORY_SEPARATOR . 'storage',
            'storage/framework/cache' => $base . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'cache',
            'storage/framework/views' => $base . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'views',
            'storage/logs'            => $base . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs',
            'bootstrap/cache'         => $base . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'cache',
        ];

        $results = [];
        foreach ($dirs as $label => $path) {
            if (!is_dir($path)) {
                @mkdir($path, 0775, true);
            }
            $results[] = [
                'path'     => $label,
                'writable' => is_writable($path),
            ];
        }

        return $results;
    }

    /**
     * Fallback standalone clean HTML renderer for the Web Installer.
     */
    protected function renderInstallHtml(array $requirements, array $permissions, bool $canProceed): string
    {
        $reqRows = '';
        foreach ($requirements as $r) {
            $badge = $r['status'] ? '<span class="badge pass">✔ PASS</span>' : '<span class="badge fail">✖ FAIL</span>';
            $reqRows .= "<tr><td><strong>{$r['name']}</strong></td><td>{$r['required']}</td><td>{$r['current']}</td><td style='text-align:right'>{$badge}</td></tr>";
        }

        $permRows = '';
        foreach ($permissions as $p) {
            $badge = $p['writable'] ? '<span class="badge pass">✔ Writable</span>' : '<span class="badge fail">✖ Not Writable</span>';
            $permRows .= "<tr><td><strong>{$p['path']}</strong></td><td style='text-align:right'>{$badge}</td></tr>";
        }

        $btnDisabled = $canProceed ? '' : 'disabled';
        $btnNotice = $canProceed ? '' : '<p class="notice error">⚠ Please resolve missing server requirements or permissions before proceeding.</p>';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NetFusion Framework — Web Setup Wizard</title>
    <style>
        :root { --primary: #2563eb; --primary-dark: #1d4ed8; --success: #16a34a; --danger: #dc2626; --bg: #0f172a; --card: #1e293b; --text: #f8fafc; --text-muted: #94a3b8; --border: #334155; }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        body { background-color: var(--bg); color: var(--text); padding: 40px 20px; display: flex; justify-content: center; }
        .installer-card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; width: 100%; max-width: 800px; padding: 36px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5); }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 1px solid var(--border); padding-bottom: 20px; }
        .header h1 { font-size: 26px; color: #fff; margin-bottom: 6px; }
        .header p { color: var(--text-muted); font-size: 14px; }
        .steps { display: flex; justify-content: space-between; margin-bottom: 30px; background: #0f172a; border-radius: 8px; padding: 12px; border: 1px solid var(--border); }
        .step { font-size: 13px; font-weight: 600; color: var(--text-muted); display: flex; align-items: center; gap: 8px; }
        .step.active { color: #60a5fa; }
        .step-num { width: 22px; height: 22px; border-radius: 50%; background: var(--border); display: inline-flex; align-items: center; justify-content: center; font-size: 11px; }
        .step.active .step-num { background: var(--primary); color: #fff; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; font-size: 14px; }
        th, td { padding: 10px 12px; border-bottom: 1px solid var(--border); text-align: left; }
        th { color: var(--text-muted); font-weight: 600; }
        .badge { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: bold; }
        .badge.pass { background: rgba(22, 163, 74, 0.2); color: #4ade80; border: 1px solid rgba(74, 222, 128, 0.4); }
        .badge.fail { background: rgba(220, 38, 38, 0.2); color: #f87171; border: 1px solid rgba(248, 113, 113, 0.4); }
        .section-title { font-size: 16px; margin: 20px 0 10px 0; color: #93c5fd; border-left: 4px solid var(--primary); padding-left: 10px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        label { font-size: 13px; font-weight: 500; color: var(--text-muted); }
        input, select { background: #0f172a; border: 1px solid var(--border); border-radius: 6px; padding: 10px 12px; color: #fff; font-size: 14px; outline: none; }
        input:focus, select:focus { border-color: var(--primary); }
        .actions { display: flex; justify-content: space-between; align-items: center; margin-top: 30px; border-top: 1px solid var(--border); padding-top: 20px; }
        button { background: var(--primary); color: #fff; border: none; border-radius: 6px; padding: 12px 24px; font-size: 14px; font-weight: 600; cursor: pointer; transition: 0.2s; }
        button:hover { background: var(--primary-dark); }
        button:disabled { background: var(--border); color: var(--text-muted); cursor: not-allowed; }
        .btn-test { background: #334155; color: #cbd5e1; padding: 10px 18px; }
        .btn-test:hover { background: #475569; }
        .notice { padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
        .notice.error { background: rgba(220, 38, 38, 0.15); border: 1px solid rgba(248, 113, 113, 0.3); color: #fca5a5; }
        #dbMsg { font-size: 13px; margin-top: 8px; }
    </style>
</head>
<body>
    <div class="installer-card">
        <div class="header">
            <h1>NetFusion Framework</h1>
            <p>Enterprise Web Setup Wizard — Author: Md. Atiquzzaman Ashish (DotNetBD)</p>
        </div>

        <div class="steps">
            <div class="step active"><span class="step-num">1</span> Requirements</div>
            <div class="step active"><span class="step-num">2</span> Permissions</div>
            <div class="step active"><span class="step-num">3</span> Database & Admin</div>
            <div class="step"><span class="step-num">4</span> Finish</div>
        </div>

        {$btnNotice}

        <div class="section-title">1. Server Environment & PHP Extensions</div>
        <table>
            <thead><tr><th>Requirement</th><th>Required</th><th>Detected</th><th style="text-align:right">Status</th></tr></thead>
            <tbody>{$reqRows}</tbody>
        </table>

        <div class="section-title">2. Directory Write Permissions</div>
        <table>
            <thead><tr><th>Path</th><th style="text-align:right">Permission</th></tr></thead>
            <tbody>{$permRows}</tbody>
        </table>

        <form id="setupForm">
            <div class="section-title">3. Database Connection Configuration</div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Database Driver</label>
                    <select name="db_driver" id="db_driver">
                        <option value="mysql">MySQL / MariaDB</option>
                        <option value="pgsql">PostgreSQL</option>
                        <option value="sqlite">SQLite</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Host</label>
                    <input type="text" name="db_host" id="db_host" value="127.0.0.1">
                </div>
                <div class="form-group">
                    <label>Port</label>
                    <input type="text" name="db_port" id="db_port" value="3306">
                </div>
                <div class="form-group">
                    <label>Database Name</label>
                    <input type="text" name="db_database" id="db_database" value="netfusion">
                </div>
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="db_username" id="db_username" value="root">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="db_password" id="db_password" placeholder="Database password">
                </div>
            </div>
            <button type="button" class="btn-test" id="btnTestDb">Test Database Connection</button>
            <div id="dbMsg"></div>

            <div class="section-title" style="margin-top:30px">4. Administrator & Application Details</div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Application Name</label>
                    <input type="text" name="app_name" value="NetFusion Enterprise">
                </div>
                <div class="form-group">
                    <label>Application URL</label>
                    <input type="text" name="app_url" value="http://localhost">
                </div>
                <div class="form-group">
                    <label>Super Admin Name</label>
                    <input type="text" name="admin_name" value="Super Administrator">
                </div>
                <div class="form-group">
                    <label>Admin Email Address</label>
                    <input type="email" name="admin_email" value="admin@example.com">
                </div>
                <div class="form-group">
                    <label>Admin Password</label>
                    <input type="password" name="admin_password" value="secret123">
                </div>
            </div>

            <div class="section-title" style="margin-top:30px">5. DotNetBD Enterprise License Activation (Optional)</div>
            <div style="background:rgba(59,130,246,0.1); border:1px solid rgba(59,130,246,0.3); border-radius:6px; padding:12px; margin-bottom:16px; font-size:13px; color:#bfdbfe;">
                ℹ Enter your 20-character DotNetBD License Key and registered Company Name to activate immediately. If you don't have one, leave blank to continue.
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>License Key</label>
                    <input type="text" name="license_key" placeholder="e.g. NF-8472-9102-3847">
                </div>
                <div class="form-group">
                    <label>Company Name</label>
                    <input type="text" name="company_name" placeholder="e.g. DotNetBD Client">
                </div>
            </div>

            <div class="actions">
                <span style="font-size:13px; color:var(--text-muted)">License: MIT &bull; DotNetBD</span>
                <button type="submit" id="btnSubmit" {$btnDisabled}>Install NetFusion Framework</button>
            </div>
        </form>
    </div>

    <script>
        document.getElementById('btnTestDb').addEventListener('click', async function() {
            const msg = document.getElementById('dbMsg');
            msg.innerHTML = '<span style="color:#93c5fd">Testing database handshake...</span>';
            const data = {
                driver: document.getElementById('db_driver').value,
                host: document.getElementById('db_host').value,
                port: document.getElementById('db_port').value,
                database: document.getElementById('db_database').value,
                username: document.getElementById('db_username').value,
                password: document.getElementById('db_password').value
            };
            try {
                const res = await fetch('/install/check-db', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const json = await res.json();
                if (json.success) {
                    msg.innerHTML = '<span style="color:#4ade80">' + json.message + '</span>';
                } else {
                    msg.innerHTML = '<span style="color:#f87171">' + json.message + '</span>';
                }
            } catch (err) {
                msg.innerHTML = '<span style="color:#f87171">Failed to contact server.</span>';
            }
        });

        document.getElementById('setupForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSubmit');
            btn.disabled = true;
            btn.innerText = 'Installing, please wait...';

            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());

            try {
                const res = await fetch('/install/setup', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const json = await res.json();
                if (json.success && json.redirect) {
                    window.location.href = json.redirect;
                } else {
                    alert('Installation error: ' + (json.message || 'Unknown error'));
                    btn.disabled = false;
                    btn.innerText = 'Install NetFusion Framework';
                }
            } catch (err) {
                alert('Installation request failed: ' + err.message);
                btn.disabled = false;
                btn.innerText = 'Install NetFusion Framework';
            }
        });
    </script>
</body>
</html>
HTML;
    }

    /**
     * Fallback standalone HTML renderer for installation completion.
     */
    protected function renderCompletedHtml(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NetFusion Framework — Installation Completed</title>
    <style>
        :root { --primary: #2563eb; --success: #16a34a; --bg: #0f172a; --card: #1e293b; --text: #f8fafc; --text-muted: #94a3b8; --border: #334155; }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        body { background-color: var(--bg); color: var(--text); padding: 60px 20px; display: flex; justify-content: center; }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; width: 100%; max-width: 600px; padding: 40px; text-align: center; }
        .icon { width: 64px; height: 64px; border-radius: 50%; background: rgba(22, 163, 74, 0.2); border: 2px solid var(--success); color: #4ade80; display: inline-flex; align-items: center; justify-content: center; font-size: 32px; margin-bottom: 20px; }
        h1 { font-size: 24px; color: #fff; margin-bottom: 12px; }
        p { color: var(--text-muted); font-size: 15px; line-height: 1.6; margin-bottom: 24px; }
        .btn { display: inline-block; background: var(--primary); color: #fff; padding: 12px 30px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 15px; }
        .btn:hover { background: #1d4ed8; }
        .notice { font-size: 13px; color: #64748b; margin-top: 24px; border-top: 1px solid var(--border); padding-top: 16px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">✓</div>
        <h1>Installation Completed!</h1>
        <p>Congratulations! <strong>NetFusion Framework</strong> has been successfully installed, configured, and secured. The installation lock has been created at <code>storage/installed</code>.</p>
        <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
            <a href="/admin/dashboard" class="btn" style="background: #2563eb;">🚀 Go to Executive Dashboard</a>
        </div>
        <div class="notice">
            NetFusion Framework &bull; Author: Md. Atiquzzaman Ashish &bull; DotNetBD
        </div>
    </div>
</body>
</html>
HTML;
    }
}
