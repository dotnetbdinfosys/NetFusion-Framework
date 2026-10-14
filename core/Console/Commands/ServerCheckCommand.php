<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/ServerCheckCommand.php
| Module    : Core\Console\Commands
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : Universal Linux, Unix, and Windows server environment diagnostic doctor.
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
use NetFusion\Core\Database\DatabaseManager;
use PDO;

class ServerCheckCommand extends Command
{
    protected string $name = 'server:check';
    protected string $description = 'Perform exhaustive health and environment diagnostics for Linux/Unix/Windows hosting';

    public function handle(): int
    {
        $this->line("====================================================================");
        $this->line("   NetFusion Framework — Universal Server Environment Doctor");
        $this->line("====================================================================");
        $this->line();

        $criticalFailures = 0;
        $warnings = 0;

        // 1. Operating System Detection
        $osFamily = PHP_OS_FAMILY;
        $distro = $this->detectLinuxDistro();
        $uname = php_uname('s') . ' ' . php_uname('r') . ' (' . php_uname('m') . ')';

        $this->info("● Operating System & Environment:");
        $this->line("  OS Family      : " . $osFamily);
        $this->line("  Distribution   : " . ($distro ?: 'Generic ' . $osFamily));
        $this->line("  Kernel / Arch  : " . $uname);
        $this->line("  Server SAPI    : " . php_sapi_name());
        $this->line();

        // 2. PHP Runtime
        $this->info("● PHP Runtime & Memory Limits:");
        $phpVersion = PHP_VERSION;
        if (version_compare($phpVersion, '8.2.0', '>=')) {
            $this->success("PHP Version    : {$phpVersion} (>= 8.2.0 required)");
        } else {
            $this->error("PHP Version    : {$phpVersion} (FAIL: NetFusion requires PHP 8.2.0 or higher)");
            $criticalFailures++;
        }

        $memoryLimit = ini_get('memory_limit');
        $this->line("  Memory Limit   : " . ($memoryLimit ?: 'Unlimited'));
        $maxExecution = ini_get('max_execution_time');
        $this->line("  Execution Time : " . ($maxExecution ? "{$maxExecution}s" : 'Unlimited'));
        $this->line();

        // 3. Core Required PHP Extensions
        $this->info("● Core Required Extensions:");
        $requiredExtensions = [
            'pdo'       => 'Database PDO Abstraction',
            'openssl'   => 'Cryptographic Signatures & JWT',
            'curl'      => 'HTTP Webhooks & S3 REST Client',
            'mbstring'  => 'Multibyte String Operations',
            'bcmath'    => 'Precision Double-Entry Ledger Math',
            'json'      => 'JSON Serialization & API Platform',
            'ctype'     => 'Character Type Checking',
            'filter'    => 'Input Filtering & Sanitization',
            'tokenizer' => 'Reflection & HMVC Autoloading',
        ];

        foreach ($requiredExtensions as $ext => $label) {
            if (extension_loaded($ext)) {
                $this->success(sprintf("%-12s : Available (%s)", $ext, $label));
            } else {
                $this->error(sprintf("%-12s : MISSING (%s - Critical)", $ext, $label));
                $criticalFailures++;
            }
        }
        $this->line();

        // 4. Performance & Recommended Extensions
        $this->info("● Recommended & Optional Extensions:");
        $recommended = [
            'opcache' => 'OPcache Bytecode & JIT Engine',
            'gd'      => 'Image Processing & Thumbnailing',
            'zip'     => 'Zip Archives & Package Extraction',
            'pcntl'   => 'Async Process & Queue Signal Handling',
            'redis'   => 'High-Throughput Redis Cache/Queue',
        ];

        foreach ($recommended as $ext => $label) {
            if (extension_loaded($ext)) {
                $this->success(sprintf("%-12s : Available (%s)", $ext, $label));
            } else {
                $this->warn(sprintf("%-12s : Not Loaded (%s)", $ext, $label));
                $warnings++;
            }
        }
        $this->line();

        // 5. Database PDO Drivers
        $this->info("● Database PDO Drivers:");
        $availablePdo = PDO::getAvailableDrivers();
        $this->line("  Installed      : " . (!empty($availablePdo) ? implode(', ', $availablePdo) : 'None'));
        if (in_array('mysql', $availablePdo, true) || in_array('pgsql', $availablePdo, true) || in_array('sqlite', $availablePdo, true)) {
            $this->success("At least one major database driver (MySQL, PostgreSQL, SQLite) is installed.");
        } else {
            $this->error("No supported PDO database driver found (mysql, pgsql, or sqlite required)!");
            $criticalFailures++;
        }
        $this->line();

        // 6. Directory Write Permissions
        $this->info("● Filesystem & Directory Permissions:");
        $dirsToCheck = [
            'storage'                 => storage_path(),
            'storage/framework/cache' => storage_path('framework/cache'),
            'storage/framework/views' => storage_path('framework/views'),
            'storage/logs'            => storage_path('logs'),
            'storage/app/public'      => storage_path('app/public'),
            'public'                  => public_path(),
        ];

        foreach ($dirsToCheck as $name => $path) {
            if (!is_dir($path)) {
                @mkdir($path, 0775, true);
            }

            if (is_writable($path)) {
                $this->success(sprintf("%-26s : Writable (%s)", $name, $path));
            } else {
                $this->error(sprintf("%-26s : NOT WRITABLE (%s)", $name, $path));
                $criticalFailures++;
            }
        }
        $this->line();

        // 7. Environment & Live Database Connection
        $this->info("● Environment Configuration & Database Connection:");
        $envFile = base_path('.env');
        if (file_exists($envFile)) {
            $this->success(".env configuration file found.");
        } else {
            $this->warn(".env file not found (using default environment config).");
            $warnings++;
        }

        try {
            $db = $this->app->make(DatabaseManager::class)->connection();
            $db->statement("SELECT 1");
            $this->success("Database connection verified successfully (Driver: " . $db->getDriverName() . ").");
        } catch (\Throwable $e) {
            $this->warn("Live database connection could not be established: " . $e->getMessage());
            $warnings++;
        }

        // 8. Public Storage Symlink
        $storageSymlink = public_path('storage');
        if (is_link($storageSymlink) || is_dir($storageSymlink)) {
            $this->success("Public storage symlink/junction is configured.");
        } else {
            $this->warn("Public storage symlink is not linked yet (Run: php netfusion storage:link).");
            $warnings++;
        }
        $this->line();

        // Final Verdict
        $this->line("====================================================================");
        if ($criticalFailures === 0) {
            $this->success("SERVER READY: Your server is fully functional and optimized for NetFusion Framework!");
            if ($warnings > 0) {
                $this->warn("Note: {$warnings} optional recommendation(s) were noted above.");
            }
            $this->line("====================================================================");
            return 0;
        }

        $this->error("SERVER ISSUES DETECTED: {$criticalFailures} critical requirement(s) failed.");
        $this->line("Please resolve the critical errors above before deploying to production.");
        $this->line("====================================================================");
        return 1;
    }

    protected function detectLinuxDistro(): ?string
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return null;
        }

        if (file_exists('/etc/os-release')) {
            $content = file_get_contents('/etc/os-release');
            if (preg_match('/PRETTY_NAME="([^"]+)"/', $content, $m)) {
                return $m[1];
            }
            if (preg_match('/NAME="([^"]+)"/', $content, $m)) {
                return $m[1];
            }
        }

        if (file_exists('/etc/redhat-release')) {
            return trim((string)file_get_contents('/etc/redhat-release'));
        }

        if (file_exists('/etc/debian_version')) {
            return 'Debian ' . trim((string)file_get_contents('/etc/debian_version'));
        }

        return null;
    }
}
