<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Security/SecurityAuditor.php
| Module    : Core\Security
| Namespace : NetFusion\Core\Security
| Purpose   : Automated vulnerability scanner, configuration auditor, and compliance checker.
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

namespace NetFusion\Core\Security;

class SecurityAuditor
{
    protected string $basePath;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath ?? (function_exists('base_path') ? base_path() : dirname(__DIR__, 2));
    }

    /**
     * Run full security compliance and vulnerability audit.
     *
     * @return array{
     *     score: int,
     *     rating: string,
     *     passed: int,
     *     warnings: int,
     *     critical: int,
     *     checks: array<array{name: string, category: string, status: string, message: string, recommendation: string}>
     * }
     */
    public function audit(): array
    {
        $checks = [];

        // 1. App Environment & Debug Mode
        $checks[] = $this->checkDebugMode();

        // 2. Encryption Key Strength
        $checks[] = $this->checkAppKey();

        // 3. DotEnv Protection & Version Control
        $checks[] = $this->checkDotEnv();

        // 4. Git Exposure in Public Web Root
        $checks[] = $this->checkGitExposure();

        // 5. Database Credentials & Privileges
        $checks[] = $this->checkDatabaseSecurity();

        // 6. PHP Runtime Directives (display_errors, expose_php, etc.)
        $checks[] = $this->checkPhpDirectives();

        // 7. Directory Permissions (storage & bootstrap/cache)
        $checks[] = $this->checkDirectoryPermissions();

        // 8. Session Security & HTTPS Configuration
        $checks[] = $this->checkSessionSecurity();

        // Calculate statistics and overall score
        $passed = 0;
        $warnings = 0;
        $critical = 0;

        foreach ($checks as $check) {
            if ($check['status'] === 'PASS') {
                $passed++;
            } elseif ($check['status'] === 'WARN') {
                $warnings++;
            } elseif ($check['status'] === 'CRITICAL') {
                $critical++;
            }
        }

        $total = count($checks);
        $score = $total > 0 ? (int) round((($passed * 100) + ($warnings * 50)) / ($total * 100) * 100) : 100;
        if ($critical > 0) {
            $score = min($score, 65); // Cap score if any critical failure exists
        }

        $rating = 'A';
        if ($score < 60) {
            $rating = 'F (Insecure)';
        } elseif ($score < 75) {
            $rating = 'C (Needs Hardening)';
        } elseif ($score < 90) {
            $rating = 'B (Good)';
        } else {
            $rating = 'A+ (Enterprise Hardened)';
        }

        return [
            'score'    => $score,
            'rating'   => $rating,
            'passed'   => $passed,
            'warnings' => $warnings,
            'critical' => $critical,
            'checks'   => $checks,
        ];
    }

    protected function checkDebugMode(): array
    {
        $env = strtolower((string) env('APP_ENV', 'production'));
        $debug = filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN);

        if ($env === 'production' && $debug) {
            return [
                'name'           => 'Application Debug Mode',
                'category'       => 'Core Security',
                'status'         => 'CRITICAL',
                'message'        => 'APP_DEBUG is enabled in a production environment. Stack traces with sensitive credentials may be exposed.',
                'recommendation' => 'Set APP_DEBUG=false in your .env file.',
            ];
        }

        if ($debug) {
            return [
                'name'           => 'Application Debug Mode',
                'category'       => 'Core Security',
                'status'         => 'WARN',
                'message'        => "Debug mode is active in {$env} environment.",
                'recommendation' => 'Ensure APP_DEBUG is disabled before deploying to production.',
            ];
        }

        return [
            'name'           => 'Application Debug Mode',
            'category'       => 'Core Security',
            'status'         => 'PASS',
            'message'        => 'Debug mode is disabled. Error traces are hidden from external users.',
            'recommendation' => 'Keep APP_DEBUG=false in production.',
        ];
    }

    protected function checkAppKey(): array
    {
        $key = (string) env('APP_KEY', '');

        if (empty($key)) {
            return [
                'name'           => 'Encryption Key (APP_KEY)',
                'category'       => 'Cryptography',
                'status'         => 'CRITICAL',
                'message'        => 'APP_KEY is missing. Session cookies and encrypted data cannot be authenticated.',
                'recommendation' => 'Generate a secure key: set APP_KEY=base64:... (at least 32 characters).',
            ];
        }

        if (strlen($key) < 32) {
            return [
                'name'           => 'Encryption Key (APP_KEY)',
                'category'       => 'Cryptography',
                'status'         => 'WARN',
                'message'        => 'APP_KEY is shorter than 32 characters. Encryption may be susceptible to brute-force attacks.',
                'recommendation' => 'Use a 32-character or 64-character cryptographically random secret.',
            ];
        }

        return [
            'name'           => 'Encryption Key (APP_KEY)',
            'category'       => 'Cryptography',
            'status'         => 'PASS',
            'message'        => 'APP_KEY is strong and sufficiently complex (' . strlen($key) . ' characters).',
            'recommendation' => 'Keep APP_KEY secret and never commit it to source control.',
        ];
    }

    protected function checkDotEnv(): array
    {
        $gitignorePath = $this->basePath . DIRECTORY_SEPARATOR . '.gitignore';
        $envProtected = false;

        if (file_exists($gitignorePath)) {
            $gitignore = (string) file_get_contents($gitignorePath);
            if (str_contains($gitignore, '.env')) {
                $envProtected = true;
            }
        }

        if (!$envProtected) {
            return [
                'name'           => 'Environment Secrets Protection',
                'category'       => 'Confidentiality',
                'status'         => 'WARN',
                'message'        => '.env is not explicitly ignored in .gitignore. Secrets may be accidentally committed.',
                'recommendation' => 'Add .env to your root .gitignore file.',
            ];
        }

        return [
            'name'           => 'Environment Secrets Protection',
            'category'       => 'Confidentiality',
            'status'         => 'PASS',
            'message'        => '.env is properly shielded from git source control.',
            'recommendation' => 'Ensure production .env has 0600 or 0640 file permissions.',
        ];
    }

    protected function checkGitExposure(): array
    {
        $publicGit = $this->basePath . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . '.git';

        if (file_exists($publicGit)) {
            return [
                'name'           => 'Git Repository Exposure',
                'category'       => 'Web Exposure',
                'status'         => 'CRITICAL',
                'message'        => 'The .git folder is accessible directly inside the public document root! Source code can be downloaded.',
                'recommendation' => 'Remove public/.git immediately and ensure DocumentRoot points to public/, not root.',
            ];
        }

        return [
            'name'           => 'Git Repository Exposure',
            'category'       => 'Web Exposure',
            'status'         => 'PASS',
            'message'        => 'Public document root does not contain version control metadata (.git).',
            'recommendation' => 'Maintain separation between public/ and application source directories.',
        ];
    }

    protected function checkDatabaseSecurity(): array
    {
        $user = (string) env('DB_USERNAME', '');
        $pass = (string) env('DB_PASSWORD', '');
        $env  = (string) env('APP_ENV', 'production');

        if (strtolower($env) === 'production' && strtolower($user) === 'root' && empty($pass)) {
            return [
                'name'           => 'Database Credentials',
                'category'       => 'Data Security',
                'status'         => 'CRITICAL',
                'message'        => 'Database user is root with an empty password in production!',
                'recommendation' => 'Create a dedicated unprivileged database user with a strong password.',
            ];
        }

        if (strtolower($env) === 'production' && strtolower($user) === 'root') {
            return [
                'name'           => 'Database Credentials',
                'category'       => 'Data Security',
                'status'         => 'WARN',
                'message'        => 'Database user is root. It is recommended to use an unprivileged application user.',
                'recommendation' => 'Grant privileges only on the netfusion database to a non-root user.',
            ];
        }

        return [
            'name'           => 'Database Credentials',
            'category'       => 'Data Security',
            'status'         => 'PASS',
            'message'        => 'Database user authentication is configured.',
            'recommendation' => 'Rotate production database passwords periodically.',
        ];
    }

    protected function checkPhpDirectives(): array
    {
        $allowUrlInclude = filter_var(ini_get('allow_url_include'), FILTER_VALIDATE_BOOLEAN);
        $displayErrors   = filter_var(ini_get('display_errors'), FILTER_VALIDATE_BOOLEAN);

        if ($allowUrlInclude) {
            return [
                'name'           => 'PHP Runtime Directives',
                'category'       => 'Server Hardening',
                'status'         => 'CRITICAL',
                'message'        => 'PHP directive "allow_url_include" is ON! Remote file inclusion (RFI) vulnerabilities are possible.',
                'recommendation' => 'Set allow_url_include = Off in your php.ini.',
            ];
        }

        if ($displayErrors && strtolower((string) env('APP_ENV', 'production')) === 'production') {
            return [
                'name'           => 'PHP Runtime Directives',
                'category'       => 'Server Hardening',
                'status'         => 'WARN',
                'message'        => 'display_errors is enabled in php.ini. System paths may be displayed on unhandled errors.',
                'recommendation' => 'Set display_errors = Off and log_errors = On in php.ini.',
            ];
        }

        return [
            'name'           => 'PHP Runtime Directives',
            'category'       => 'Server Hardening',
            'status'         => 'PASS',
            'message'        => 'Critical PHP security directives (allow_url_include) are properly configured.',
            'recommendation' => 'Ensure expose_php = Off is also set in production.',
        ];
    }

    protected function checkDirectoryPermissions(): array
    {
        $storage = $this->basePath . DIRECTORY_SEPARATOR . 'storage';
        $cache   = $this->basePath . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'cache';

        $storageWritable = is_writable($storage);
        $cacheWritable   = is_writable($cache);

        if (!$storageWritable || !$cacheWritable) {
            return [
                'name'           => 'Directory Permissions',
                'category'       => 'Filesystem',
                'status'         => 'WARN',
                'message'        => 'Writable directories (storage or bootstrap/cache) are not writable by the web process.',
                'recommendation' => 'Run: chmod -R 775 storage bootstrap/cache',
            ];
        }

        return [
            'name'           => 'Directory Permissions',
            'category'       => 'Filesystem',
            'status'         => 'PASS',
            'message'        => 'Storage and bootstrap/cache directories have correct write permissions.',
            'recommendation' => 'Do not use chmod 777 in production; use chmod 775 with web server group ownership.',
        ];
    }

    protected function checkSessionSecurity(): array
    {
        $cookieSecure = filter_var(ini_get('session.cookie_secure'), FILTER_VALIDATE_BOOLEAN);
        $cookieHttpOnly = filter_var(ini_get('session.cookie_httponly'), FILTER_VALIDATE_BOOLEAN);

        if (!$cookieHttpOnly) {
            return [
                'name'           => 'Session Cookie Hardening',
                'category'       => 'Session Security',
                'status'         => 'WARN',
                'message'        => 'session.cookie_httponly is disabled in php.ini. Session cookies may be accessed by JavaScript (XSS).',
                'recommendation' => 'Set session.cookie_httponly = 1 in php.ini.',
            ];
        }

        return [
            'name'           => 'Session Cookie Hardening',
            'category'       => 'Session Security',
            'status'         => 'PASS',
            'message'        => 'Session cookies are protected with HttpOnly flag.',
            'recommendation' => 'Ensure session.cookie_secure = 1 when running over HTTPS.',
        ];
    }
}
