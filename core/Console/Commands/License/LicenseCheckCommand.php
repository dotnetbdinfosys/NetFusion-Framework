<?php
/**
 * NetFusion Framework - Enterprise License Fabric
 *
 * CLI Command: license:check
 *
 * Validates local active license against DotNetBD License CMS and displays status.
 *
 * Usage:
 *   php netfusion license:check [--force]
 *
 * @package    NetFusion\Core\Console\Commands\License
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Console\Commands\License;

use NetFusion\Core\Console\Command;
use NetFusion\Core\Foundation\License\LicenseManager;

class LicenseCheckCommand extends Command
{
    protected string $name = 'license:check';
    protected string $description = 'Check and validate current DotNetBD software license status.';

    public function handle(): int
    {
        $manager = new LicenseManager();
        $status  = $manager->status();

        $this->line('');
        $this->line('=== DotNetBD License CMS — License Status Inspection ===');

        if (empty($status['is_active'])) {
            $this->error('Status: UNLICENSED OR EXPIRED');
            $this->line(sprintf('Message: %s', $status['message']));
            $this->line('');
            $this->line('To activate, run:');
            $this->line('  php netfusion license:activate <LICENSE_KEY> <COMPANY_NAME>');
            $this->line('');
            return 1;
        }

        $this->line('Status        : ✅ ACTIVE & VERIFIED');
        $this->line(sprintf('License Key   : %s', $status['license_key']));
        $this->line(sprintf('Company Name  : %s', $status['company_name']));
        $this->line(sprintf('Bound Domain  : %s', $status['domain']));
        $this->line(sprintf('Server Limit  : %s server(s)', $status['max_servers']));
        $this->line(sprintf('Expiration    : %s', $status['expires_at']));
        if ($status['days_remaining'] !== null) {
            $this->line(sprintf('Days Remaining: %d days', $status['days_remaining']));
        }
        $this->line(sprintf('Activated At  : %s', $status['activated_at']));
        $this->line(sprintf('Last Heartbeat: %s', $status['last_validated_at']));
        $this->line(str_repeat('-', 60));
        $this->line('');

        return 0;
    }
}
