<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/SecurityAuditCommand.php
| Module    : Core\Console\Commands
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : CLI command running automated vulnerability scanning and security compliance checks.
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
use NetFusion\Core\Security\SecurityAuditor;

class SecurityAuditCommand extends Command
{
    protected string $name = 'security:audit';
    protected string $description = 'Perform exhaustive vulnerability and configuration security compliance audit';

    public function handle(): int
    {
        $this->line("====================================================================");
        $this->line("   NetFusion Framework — Enterprise Security Compliance Auditor");
        $this->line("====================================================================");
        $this->line();

        $auditor = new SecurityAuditor();
        $results = $auditor->audit();

        $this->line("Scanning application environment, cryptographic keys, and permissions...\n");

        foreach ($results['checks'] as $check) {
            $status = $check['status'];
            $icon = match ($status) {
                'PASS'     => '✔ [PASS]',
                'WARN'     => '⚠ [WARN]',
                'CRITICAL' => '✖ [FAIL]',
                default    => '● [INFO]',
            };

            $header = sprintf("%-10s %-32s [%s]", $icon, $check['name'], $check['category']);

            if ($status === 'PASS') {
                $this->info($header);
            } elseif ($status === 'WARN') {
                $this->warn($header);
            } else {
                $this->error($header);
            }

            $this->line("           " . $check['message']);
            if ($status !== 'PASS') {
                $this->line("           💡 Remedy: " . $check['recommendation']);
            }
            $this->line();
        }

        $this->line("====================================================================");
        $this->line(sprintf("Security Score : %d%%", $results['score']));
        $this->line(sprintf("Grade Rating   : %s", $results['rating']));
        $this->line(sprintf("Summary        : %d Passed, %d Warnings, %d Critical Failures", $results['passed'], $results['warnings'], $results['critical']));
        $this->line("====================================================================");

        if ($results['critical'] > 0) {
            $this->error("SECURITY ALERT: Critical vulnerabilities detected! Resolve issues before production.");
            return 1;
        }

        if ($results['warnings'] > 0) {
            $this->warn("Security status acceptable with recommended hardening actions.");
            return 0;
        }

        $this->info("✔ Application is fully hardened and production security compliant!");
        return 0;
    }
}
