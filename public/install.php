<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework — Standalone Web Installer Wizard
|--------------------------------------------------------------------------
|
| File      : public/install.php
| Purpose   : Browser-based interactive setup wizard for first-run deployment
|             on Shared Hosting, CloudPanel, cPanel, VPS, or Docker.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| Website   : https://www.dotnetbd.com
| Copyright : © DotNetBD. All rights reserved.
| License   : MIT
| Version   : 1.1.0 (Fixed Enterprise Release)
|
|--------------------------------------------------------------------------
*/

error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', '0');

$baseDir = dirname(__DIR__);
$lockFile = $baseDir . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'installed';

// Auto-detect base URL
$isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
$protocol = $isHttps ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$currentAppUrl = $protocol . $host;

// -----------------------------------------------------------------------------
// 1. Check if application is already installed
// -----------------------------------------------------------------------------
if (file_exists($lockFile)) {
    header('Content-Type: text/html; charset=UTF-8');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>NetFusion Framework — Already Installed</title>
        <style>
            :root { --bg: #0f172a; --card: #1e293b; --text: #f8fafc; --text-muted: #94a3b8; --border: #334155; --primary: #3b82f6; --success: #10b981; }
            * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
            body { background-color: var(--bg); color: var(--text); padding: 60px 20px; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
            .card { background: var(--card); border: 1px solid var(--border); border-radius: 14px; max-width: 600px; width: 100%; padding: 40px; text-align: center; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); }
            .badge { display: inline-block; padding: 6px 16px; border-radius: 20px; font-size: 13px; font-weight: 600; background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.4); margin-bottom: 20px; }
            h1 { font-size: 24px; margin-bottom: 12px; }
            p { color: var(--text-muted); font-size: 15px; line-height: 1.6; margin-bottom: 28px; }
            .btn-group { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
            a.btn { text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; font-size: 14px; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; }
            .btn-primary { background: var(--primary); color: #fff; }
            .btn-primary:hover { background: #2563eb; }
            .btn-secondary { background: #334155; color: #f8fafc; }
            .btn-secondary:hover { background: #475569; }
            .footer { margin-top: 30px; font-size: 12px; color: #64748b; border-top: 1px solid var(--border); padding-top: 16px; }
        </style>
    </head>
    <body>
        <div class="card">
            <span class="badge">✔ SYSTEM INSTALLED & LOCKED</span>
            <h1>NetFusion Framework</h1>
            <p>The framework has already been successfully installed and secured on this server. To protect your data, the web installer is locked.<br><br>If you need to re-run the setup wizard, remove the lock file at <code>storage/installed</code>.</p>
            <div class="btn-group">
                <a href="/" class="btn btn-secondary">🌐 Visit Homepage</a>
                <a href="/license" class="btn btn-secondary">🛡️ License Portal</a>
                <a href="/admin/dashboard" class="btn btn-primary">⚡ Admin Dashboard</a>
            </div>
            <div class="footer">
                Developed by DotNetBD &bull; https://www.dotnetbd.com
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// -----------------------------------------------------------------------------
// 2. Handle AJAX API requests
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=UTF-8');
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $action = $input['action'] ?? '';

    // Action: Test Database Connection
    if ($action === 'check_db') {
        $driver   = (string) ($input['driver'] ?? 'mysql');
        $host     = (string) ($input['host'] ?? '127.0.0.1');
        $port     = (int) (($input['port'] ?? 0) ?: ($driver === 'pgsql' ? 5432 : 3306));
        $database = (string) ($input['database'] ?? '');
        $username = (string) ($input['username'] ?? '');
        $password = (string) ($input['password'] ?? '');

        try {
            if ($driver === 'sqlite') {
                $dbPath = $database ?: ($baseDir . '/storage/database.sqlite');
                $pdo = new PDO("sqlite:{$dbPath}", null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            } elseif ($driver === 'pgsql') {
                $dsn = "pgsql:host={$host};port={$port};dbname={$database}";
                $pdo = new PDO($dsn, $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            } else {
                $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
                $pdo = new PDO($dsn, $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            }
            echo json_encode(['success' => true, 'message' => '✔ Database connection established successfully!']);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()]);
        }
        exit;
    }

    // Action: Execute Full Installation
    if ($action === 'setup') {
        $appName     = trim((string) ($input['app_name'] ?? 'NetFusion Enterprise'));
        $appUrl      = rtrim(trim((string) ($input['app_url'] ?? $currentAppUrl)), '/');
        $driver      = (string) ($input['db_driver'] ?? 'mysql');
        $host        = (string) ($input['db_host'] ?? '127.0.0.1');
        $port        = (string) (($input['db_port'] ?? '') ?: ($driver === 'pgsql' ? '5432' : '3306'));
        $database    = (string) ($input['db_database'] ?? 'netfusion');
        $username    = (string) ($input['db_username'] ?? 'root');
        $password    = (string) ($input['db_password'] ?? '');

        $adminName   = (string) ($input['admin_name'] ?? 'Super Administrator');
        $adminEmail  = (string) ($input['admin_email'] ?? 'admin@example.com');
        $adminPass   = (string) ($input['admin_password'] ?? 'secret123');

        $licenseKey  = trim((string) ($input['license_key'] ?? ''));
        $companyName = trim((string) ($input['company_name'] ?? ''));

        try {
            // A. Create required directories
            $requiredDirs = [
                $baseDir . '/storage',
                $baseDir . '/storage/framework',
                $baseDir . '/storage/framework/cache',
                $baseDir . '/storage/framework/views',
                $baseDir . '/storage/framework/sessions',
                $baseDir . '/storage/logs',
                $baseDir . '/storage/app',
                $baseDir . '/storage/app/public',
                $baseDir . '/bootstrap/cache',
            ];
            foreach ($requiredDirs as $dir) {
                if (!is_dir($dir)) {
                    @mkdir($dir, 0775, true);
                }
            }

            // B. If SQLite, prepare database file
            if ($driver === 'sqlite') {
                $sqlitePath = $database ?: 'storage/database.sqlite';
                $fullSqlite = str_starts_with($sqlitePath, '/') || preg_match('/^[a-zA-Z]:\\\\/', $sqlitePath)
                    ? $sqlitePath
                    : $baseDir . DIRECTORY_SEPARATOR . $sqlitePath;
                if (!file_exists($fullSqlite)) {
                    @touch($fullSqlite);
                }
            }

            // C. Generate 32-byte cryptographic APP_KEY
            $appKey = 'base64:' . base64_encode(random_bytes(32));

            // D. Generate .env file
            $envPath = $baseDir . DIRECTORY_SEPARATOR . '.env';
            $envContent = <<<ENV
# ==============================================================================
# NetFusion Framework — Production Environment Configuration
# Generated by Web Setup Wizard on {$currentAppUrl}
# ==============================================================================

APP_NAME="{$appName}"
APP_ENV=production
APP_KEY={$appKey}
APP_DEBUG=false
APP_URL={$appUrl}
APP_VERSION=1.1.0
APP_TIMEZONE=Asia/Dhaka
APP_LOCALE=en

LOG_CHANNEL=daily
LOG_LEVEL=info

DB_DRIVER={$driver}
DB_CONNECTION={$driver}
DB_HOST={$host}
DB_PORT={$port}
DB_DATABASE={$database}
DB_USERNAME={$username}
DB_PASSWORD={$password}

CACHE_STORE=file
QUEUE_DRIVER=database
STORAGE_DISK=public
ENV;
            file_put_contents($envPath, $envContent);

            // E. Database Tables Initialization & Admin Account
            try {
                if ($driver === 'sqlite') {
                    $pdo = new PDO("sqlite:{$fullSqlite}", null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
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

                // Insert Super Admin account if not existing
                $chk = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $chk->execute([$adminEmail]);
                if (!$chk->fetch()) {
                    $passHash = password_hash($adminPass, PASSWORD_BCRYPT);
                    $ins = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')");
                    $ins->execute([$adminName, $adminEmail, $passHash]);
                }
            } catch (Throwable $dbErr) {
                // Non-blocking database table setup error
            }

            // F. DotNetBD License CMS Activation (if provided)
            $licenseActivated = false;
            $licenseMsg = 'License activation skipped. You can activate anytime from /license.';
            if (!empty($licenseKey) && !empty($companyName)) {
                $autoloadFile = $baseDir . '/vendor/autoload.php';
                if (file_exists($autoloadFile)) {
                    require_once $autoloadFile;
                }
                if (class_exists(\NetFusion\Core\Foundation\License\LicenseManager::class)) {
                    try {
                        $licManager = new \NetFusion\Core\Foundation\License\LicenseManager();
                        $licResult = $licManager->activate($licenseKey, $companyName, parse_url($appUrl, PHP_URL_HOST));
                        if (!empty($licResult['success'])) {
                            $licenseActivated = true;
                            $licenseMsg = '✔ DotNetBD License activated successfully!';
                        }
                    } catch (Throwable $licErr) {
                        $licenseMsg = 'License activation note: ' . $licErr->getMessage();
                    }
                }
            }

            // G. Write storage/installed lock file
            $lockData = json_encode([
                'installed_at'      => date('c'),
                'framework'         => 'NetFusion Framework',
                'version'           => '1.1.0',
                'app_name'          => $appName,
                'app_url'           => $appUrl,
                'admin_email'       => $adminEmail,
                'license_activated' => $licenseActivated,
                'author'            => 'Md. Atiquzzaman Ashish (DotNetBD)',
            ], JSON_PRETTY_PRINT);
            file_put_contents($lockFile, $lockData);

            echo json_encode([
                'success'     => true,
                'redirect'    => 'install.php?step=completed',
                'license_msg' => $licenseMsg,
            ]);
        } catch (Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Installation failed: ' . $e->getMessage(),
            ]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

// -----------------------------------------------------------------------------
// 3. Completed Screen View
// -----------------------------------------------------------------------------
if (isset($_GET['step']) && $_GET['step'] === 'completed') {
    header('Content-Type: text/html; charset=UTF-8');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>NetFusion Framework — Installation Completed</title>
        <style>
            :root { --bg: #0f172a; --card: #1e293b; --text: #f8fafc; --text-muted: #94a3b8; --border: #334155; --primary: #3b82f6; --success: #10b981; }
            * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
            body { background-color: var(--bg); color: var(--text); padding: 60px 20px; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
            .card { background: var(--card); border: 1px solid var(--border); border-radius: 14px; max-width: 620px; width: 100%; padding: 44px; text-align: center; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); }
            .icon { width: 72px; height: 72px; border-radius: 50%; background: rgba(16, 185, 129, 0.15); border: 2px solid var(--success); color: #34d399; display: inline-flex; align-items: center; justify-content: center; font-size: 36px; margin-bottom: 24px; }
            h1 { font-size: 26px; margin-bottom: 12px; }
            p { color: var(--text-muted); font-size: 15px; line-height: 1.6; margin-bottom: 28px; }
            .info-box { background: #0f172a; border: 1px solid var(--border); border-radius: 8px; padding: 16px; margin-bottom: 28px; text-align: left; font-size: 14px; }
            .info-box div { margin-bottom: 8px; display: flex; justify-content: space-between; }
            .info-box div:last-child { margin-bottom: 0; }
            .info-box strong { color: #cbd5e1; }
            .btn-group { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
            a.btn { text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; font-size: 14px; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; }
            .btn-primary { background: var(--primary); color: #fff; }
            .btn-primary:hover { background: #2563eb; }
            .btn-success { background: var(--success); color: #fff; }
            .btn-success:hover { background: #059669; }
            .btn-secondary { background: #334155; color: #f8fafc; }
            .btn-secondary:hover { background: #475569; }
            .footer { margin-top: 30px; font-size: 12px; color: #64748b; border-top: 1px solid var(--border); padding-top: 16px; }
        </style>
    </head>
    <body>
        <div class="card">
            <div class="icon">✓</div>
            <h1>ইনস্টলেশন সফলভাবে সম্পন্ন হয়েছে!</h1>
            <p>Congratulations! <strong>NetFusion Framework</strong> has been successfully configured, secured, and initialized on your server.</p>
            
            <div class="info-box">
                <div><span>Application URL:</span> <strong><?= htmlspecialchars($currentAppUrl) ?></strong></div>
                <div><span>Installation Status:</span> <strong style="color:#34d399">Active & Locked</strong></div>
                <div><span>Lock File:</span> <code>storage/installed</code></div>
            </div>

            <div class="btn-group">
                <a href="/" class="btn btn-secondary">🌐 Visit Site</a>
                <a href="/license" class="btn btn-success">🛡️ License Portal</a>
                <a href="/admin/dashboard" class="btn btn-primary">⚡ Admin Dashboard</a>
            </div>

            <div class="footer">
                Developed by DotNetBD &bull; https://www.dotnetbd.com &bull; Author: Md. Atiquzzaman Ashish
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// -----------------------------------------------------------------------------
// 4. Wizard View: Requirements & Permissions Analysis
// -----------------------------------------------------------------------------
$reqs = [
    ['name' => 'PHP Version', 'required' => '>= 8.2.0', 'current' => PHP_VERSION, 'status' => version_compare(PHP_VERSION, '8.2.0', '>=')],
    ['name' => 'PDO Extension', 'required' => 'Enabled', 'current' => extension_loaded('pdo') ? 'Enabled' : 'Disabled', 'status' => extension_loaded('pdo')],
    ['name' => 'OpenSSL Extension', 'required' => 'Enabled', 'current' => extension_loaded('openssl') ? 'Enabled' : 'Disabled', 'status' => extension_loaded('openssl')],
    ['name' => 'Mbstring Extension', 'required' => 'Enabled', 'current' => extension_loaded('mbstring') ? 'Enabled' : 'Disabled', 'status' => extension_loaded('mbstring')],
    ['name' => 'cURL Extension', 'required' => 'Enabled', 'current' => extension_loaded('curl') ? 'Enabled' : 'Disabled', 'status' => extension_loaded('curl')],
    ['name' => 'JSON Extension', 'required' => 'Enabled', 'current' => extension_loaded('json') ? 'Enabled' : 'Disabled', 'status' => extension_loaded('json')],
    ['name' => 'BCMath Extension', 'required' => 'Enabled', 'current' => extension_loaded('bcmath') ? 'Enabled' : 'Disabled', 'status' => extension_loaded('bcmath')],
    ['name' => 'CType Extension', 'required' => 'Enabled', 'current' => extension_loaded('ctype') ? 'Enabled' : 'Disabled', 'status' => extension_loaded('ctype')],
];

$permissionDirs = [
    'storage'           => $baseDir . '/storage',
    'storage/framework' => $baseDir . '/storage/framework',
    'storage/logs'      => $baseDir . '/storage/logs',
    'bootstrap/cache'   => $baseDir . '/bootstrap/cache',
    '.env (writable)'   => $baseDir,
];

$perms = [];
$canProceed = true;

foreach ($reqs as $r) {
    if (!$r['status']) {
        $canProceed = false;
    }
}

foreach ($permissionDirs as $label => $path) {
    if (!is_dir($path) && $label !== '.env (writable)') {
        @mkdir($path, 0775, true);
    }
    $isWritable = is_writable($path);
    if (!$isWritable) {
        $canProceed = false;
    }
    $perms[] = ['path' => $label, 'writable' => $isWritable];
}

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NetFusion Framework — Universal Web Installer</title>
    <style>
        :root {
            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --bg: #0b1120;
            --card: #1e293b;
            --card-inner: #0f172a;
            --text: #f8fafc;
            --text-muted: #94a3b8;
            --border: #334155;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        body { background-color: var(--bg); color: var(--text); padding: 40px 16px; display: flex; justify-content: center; min-height: 100vh; }
        .installer-container { width: 100%; max-width: 840px; }
        .installer-card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 36px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.6); }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 1px solid var(--border); padding-bottom: 24px; }
        .header h1 { font-size: 28px; font-weight: 800; color: #fff; margin-bottom: 8px; letter-spacing: -0.5px; }
        .header h1 span { color: var(--primary); }
        .header p { color: var(--text-muted); font-size: 14px; }
        .steps { display: flex; justify-content: space-between; margin-bottom: 32px; background: var(--card-inner); border-radius: 10px; padding: 12px 18px; border: 1px solid var(--border); gap: 10px; flex-wrap: wrap; }
        .step { font-size: 13px; font-weight: 600; color: var(--text-muted); display: flex; align-items: center; gap: 8px; }
        .step.active { color: #60a5fa; }
        .step-num { width: 24px; height: 24px; border-radius: 50%; background: var(--border); display: inline-flex; align-items: center; justify-content: center; font-size: 11px; font-weight: bold; }
        .step.active .step-num { background: var(--primary); color: #fff; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; font-size: 14px; }
        th, td { padding: 12px 14px; border-bottom: 1px solid var(--border); text-align: left; }
        th { color: var(--text-muted); font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; display: inline-block; }
        .badge.pass { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.4); }
        .badge.fail { background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.4); }
        .section-title { font-size: 16px; font-weight: 700; margin: 24px 0 12px 0; color: #93c5fd; border-left: 4px solid var(--primary); padding-left: 10px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px; }
        @media (max-width: 640px) { .form-grid { grid-template-columns: 1fr; } }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        label { font-size: 13px; font-weight: 600; color: #cbd5e1; }
        label span { color: var(--text-muted); font-weight: 400; font-size: 12px; }
        input, select { background: var(--card-inner); border: 1px solid var(--border); border-radius: 8px; padding: 11px 14px; color: #fff; font-size: 14px; outline: none; transition: 0.2s; }
        input:focus, select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2); }
        .actions { display: flex; justify-content: space-between; align-items: center; margin-top: 36px; border-top: 1px solid var(--border); padding-top: 24px; }
        button { background: var(--primary); color: #fff; border: none; border-radius: 8px; padding: 13px 28px; font-size: 15px; font-weight: 600; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; }
        button:hover { background: var(--primary-dark); }
        button:disabled { background: var(--border); color: var(--text-muted); cursor: not-allowed; }
        .btn-test { background: #334155; color: #cbd5e1; padding: 10px 18px; font-size: 13px; }
        .btn-test:hover { background: #475569; }
        .notice { padding: 14px; border-radius: 8px; margin-bottom: 24px; font-size: 14px; line-height: 1.5; }
        .notice.error { background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); color: #fca5a5; }
        .notice.license-box { background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); color: #bfdbfe; }
        #dbMsg { font-size: 13px; margin-top: 10px; font-weight: 500; }
    </style>
</head>
<body>
    <div class="installer-container">
        <div class="installer-card">
            <div class="header">
                <h1>NetFusion <span>Framework</span></h1>
                <p>Enterprise Web Setup & Deployment Wizard &bull; DotNetBD Engine v5.1.8</p>
            </div>

            <div class="steps">
                <div class="step active"><span class="step-num">1</span> Requirements</div>
                <div class="step active"><span class="step-num">2</span> Permissions</div>
                <div class="step active"><span class="step-num">3</span> Database & App</div>
                <div class="step active"><span class="step-num">4</span> License</div>
                <div class="step"><span class="step-num">5</span> Finish</div>
            </div>

            <?php if (!$canProceed): ?>
                <div class="notice error">
                    ⚠ কিছু আবশ্যক সার্ভার পূর্বশর্ত পূরণ হয়নি বা ফোল্ডারে রাইট পারমিশন নেই। অনুগ্রহ করে পারমিশন দিয়ে পেজটি রিফ্রেশ করুন।
                </div>
            <?php endif; ?>

            <div class="section-title">১. সার্ভার পরিবেশ ও PHP এক্সটেনশন যাচাই (Requirements)</div>
            <table>
                <thead>
                    <tr><th>কম্পোনেন্ট</th><th>প্রয়োজন</th><th>বর্তমান ভার্সন</th><th style="text-align:right">স্ট্যাটাস</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($reqs as $r): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($r['name']) ?></strong></td>
                            <td><?= htmlspecialchars($r['required']) ?></td>
                            <td><?= htmlspecialchars($r['current']) ?></td>
                            <td style="text-align:right">
                                <span class="badge <?= $r['status'] ? 'pass' : 'fail' ?>"><?= $r['status'] ? '✔ PASS' : '✖ FAIL' ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="section-title">২. ডিরেক্টরি পারমিশন যাচাই (Write Permissions)</div>
            <table>
                <thead>
                    <tr><th>ডিরেক্টরি পাথ</th><th style="text-align:right">রাইট পারমিশন</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($perms as $p): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($p['path']) ?></strong></td>
                            <td style="text-align:right">
                                <span class="badge <?= $p['writable'] ? 'pass' : 'fail' ?>"><?= $p['writable'] ? '✔ Writable' : '✖ Not Writable' ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <form id="installerForm">
                <div class="section-title">৩. ডাটাবেস সংযোগ কনফিগারেশন (Database Setup)</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>ডাটাবেস ড্রাইভার (Driver)</label>
                        <select name="db_driver" id="db_driver">
                            <option value="mysql" selected>MySQL / MariaDB</option>
                            <option value="pgsql">PostgreSQL</option>
                            <option value="sqlite">SQLite 3 (Zero Setup)</option>
                        </select>
                    </div>
                    <div class="form-group" id="host_group">
                        <label>ডাটাবেস হোস্ট (Host)</label>
                        <input type="text" name="db_host" id="db_host" value="127.0.0.1">
                    </div>
                    <div class="form-group" id="port_group">
                        <label>ডাটাবেস পোর্ট (Port)</label>
                        <input type="text" name="db_port" id="db_port" value="3306">
                    </div>
                    <div class="form-group">
                        <label>ডাটাবেসের নাম (Database Name / File)</label>
                        <input type="text" name="db_database" id="db_database" value="netfusion">
                    </div>
                    <div class="form-group" id="user_group">
                        <label>ডাটাবেস ইউজারনেম (Username)</label>
                        <input type="text" name="db_username" id="db_username" value="root">
                    </div>
                    <div class="form-group" id="pass_group">
                        <label>ডাটাবেস পাসওয়ার্ড (Password)</label>
                        <input type="password" name="db_password" id="db_password" placeholder="Database password">
                    </div>
                </div>

                <div style="margin-bottom: 24px;">
                    <button type="button" class="btn-test" id="btnTestDb">🔍 Test Database Connection</button>
                    <div id="dbMsg"></div>
                </div>

                <div class="section-title">৪. অ্যাপ্লিকেশন ও সুপার এডমিন একাউন্ট (Application & Admin)</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>অ্যাপ্লিকেশনের নাম (App Name)</label>
                        <input type="text" name="app_name" value="NetFusion Enterprise" required>
                    </div>
                    <div class="form-group">
                        <label>অ্যাপ্লিকেশন URL (App URL)</label>
                        <input type="text" name="app_url" value="<?= htmlspecialchars($currentAppUrl) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>সুপার এডমিনের নাম (Admin Name)</label>
                        <input type="text" name="admin_name" value="Super Administrator" required>
                    </div>
                    <div class="form-group">
                        <label>এডমিন ইমেইল (Admin Email)</label>
                        <input type="email" name="admin_email" value="admin@dotnetbd.com" required>
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>এডমিন পাসওয়ার্ড (Admin Password)</label>
                        <input type="password" name="admin_password" value="secret123" required>
                    </div>
                </div>

                <div class="section-title">৫. DotNetBD Enterprise লাইসেন্স কি অ্যাক্টিভেশন (License Activation)</div>
                <div class="notice license-box">
                    ℹ আপনার কেনা ২০-সংখ্যার লাইসেন্স কি এবং নিবন্ধিত কোম্পানির নাম দিন। যদি এই মুহূর্তে লাইসেন্স কি না থাকে, তবে খালি রেখে এগিয়ে যেতে পারবেন এবং পরবর্তীতে <code>/license</code> পেজ থেকে এক্টিভ করতে পারবেন।
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>লাইসেন্স কি (License Key) <span>(Optional)</span></label>
                        <input type="text" name="license_key" placeholder="e.g. NF-8472-9102-3847">
                    </div>
                    <div class="form-group">
                        <label>নিবন্ধিত কোম্পানির নাম (Company Name) <span>(Optional)</span></label>
                        <input type="text" name="company_name" placeholder="e.g. DotNetBD Client">
                    </div>
                </div>

                <div class="actions">
                    <span style="font-size:13px; color:var(--text-muted)">DotNetBD &bull; MIT License</span>
                    <button type="submit" id="btnSubmit" <?= $canProceed ? '' : 'disabled' ?>>
                        🚀 Start Installation & Setup
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Toggle database inputs based on driver selection
        const driverSelect = document.getElementById('db_driver');
        driverSelect.addEventListener('change', function() {
            const val = this.value;
            const isSqlite = (val === 'sqlite');
            document.getElementById('host_group').style.display = isSqlite ? 'none' : 'flex';
            document.getElementById('port_group').style.display = isSqlite ? 'none' : 'flex';
            document.getElementById('user_group').style.display = isSqlite ? 'none' : 'flex';
            document.getElementById('pass_group').style.display = isSqlite ? 'none' : 'flex';

            const dbInput = document.getElementById('db_database');
            const portInput = document.getElementById('db_port');
            if (isSqlite) {
                dbInput.value = 'storage/database.sqlite';
            } else if (val === 'pgsql') {
                portInput.value = '5432';
                dbInput.value = 'netfusion';
            } else {
                portInput.value = '3306';
                dbInput.value = 'netfusion';
            }
        });

        // Test Database Handshake
        document.getElementById('btnTestDb').addEventListener('click', async function() {
            const msg = document.getElementById('dbMsg');
            msg.innerHTML = '<span style="color:#93c5fd">⏳ Connecting to database...</span>';
            const data = {
                action: 'check_db',
                driver: document.getElementById('db_driver').value,
                host: document.getElementById('db_host').value,
                port: document.getElementById('db_port').value,
                database: document.getElementById('db_database').value,
                username: document.getElementById('db_username').value,
                password: document.getElementById('db_password').value
            };
            try {
                const res = await fetch('install.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const json = await res.json();
                if (json.success) {
                    msg.innerHTML = '<span style="color:#34d399; font-weight:600;">' + json.message + '</span>';
                } else {
                    msg.innerHTML = '<span style="color:#f87171; font-weight:600;">' + json.message + '</span>';
                }
            } catch (err) {
                msg.innerHTML = '<span style="color:#f87171">Connection test error: ' + err.message + '</span>';
            }
        });

        // Submit Installer Form
        document.getElementById('installerForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSubmit');
            btn.disabled = true;
            btn.innerHTML = '⚙️ Installing & Configuring NetFusion...';

            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());
            data.action = 'setup';

            try {
                const res = await fetch('install.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const json = await res.json();
                if (json.success) {
                    window.location.href = json.redirect || 'install.php?step=completed';
                } else {
                    alert('Installation Error: ' + (json.message || 'Unknown error occurred.'));
                    btn.disabled = false;
                    btn.innerHTML = '🚀 Start Installation & Setup';
                }
            } catch (err) {
                alert('Installation request failed: ' + err.message);
                btn.disabled = false;
                btn.innerHTML = '🚀 Start Installation & Setup';
            }
        });
    </script>
</body>
</html>
