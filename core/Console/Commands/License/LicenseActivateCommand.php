<?php
/**
 * NetFusion Framework - Enterprise License Fabric
 *
 * CLI Command: license:activate
 *
 * Activates a client server domain/IP against DotNetBD License CMS (API Engine v5.1.8).
 *
 * Usage:
 *   php netfusion license:activate <license_key> <company_name> [domain]
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

class LicenseActivateCommand extends Command
{
    protected string $name = 'license:activate';
    protected string $description = 'Activate NetFusion Framework license with DotNetBD License CMS.';

    public function handle(): int
    {
        $licenseKey  = (string) ($this->argument(0) ?? '');
        $companyName = (string) ($this->argument(1) ?? '');
        $domain      = $this->argument(2) ? (string) $this->argument(2) : null;

        if (empty($licenseKey) || empty($companyName)) {
            $this->error('Error: Both license_key and company_name are required.');
            $this->line('Usage: php netfusion license:activate <LICENSE_KEY> <COMPANY_NAME> [DOMAIN]');
            $this->line('Example: php netfusion license:activate NF-8472-9102-3847 "DotNetBD Enterprise"');
            return 1;
        }

        $this->line('');
        $this->line('=== DotNetBD License CMS — Activating NetFusion Framework ===');
        $this->line(sprintf('License Key  : %s', $licenseKey));
        $this->line(sprintf('Company Name : %s', $companyName));
        $this->line('Connecting to https://lic.dotnetbd.com ...');

        try {
            $manager = new LicenseManager();
            $result = $manager->activate($licenseKey, $companyName, $domain);

            $this->line('');
            $this->line('✅ ' . ($result['message'] ?? 'License activated successfully!'));
            $this->line(str_repeat('-', 60));
            $this->line(sprintf('Registered Domain     : %s', $result['domain']));
            $this->line(sprintf('Max Servers Allowed   : %s', $result['max_servers']));
            $this->line(sprintf('Extension Level       : %s', $result['extension_limit_label']));
            $this->line(sprintf('License Expiration    : %s', $result['expires_at']));
            $this->line(str_repeat('-', 60));
            $this->line('Local license token securely stored in storage/framework/license.lic');
            $this->line('');

            return 0;
        } catch (\Throwable $e) {
            $this->line('');
            $this->error('❌ Activation Failed: ' . $e->getMessage());
            $this->line('');
            return 1;
        }
    }
}
