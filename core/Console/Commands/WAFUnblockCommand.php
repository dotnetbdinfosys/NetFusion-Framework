<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;
use NetFusion\Core\WAF\WAFManager;

class WAFUnblockCommand extends Command
{
    protected string $name = 'waf:unblock';
    protected string $signature = 'waf:unblock {ip : IP address or CIDR subnet to unblock}';
    protected string $description = 'Unblock an IP address or CIDR subnet';

    public function handle(): int
    {
        /** @var WAFManager $waf */
        $waf = function_exists('waf') ? waf() : (function_exists('app') && app()->has('waf') ? app('waf') : new WAFManager());

        $ip = (string)$this->argument(0);
        if (empty($ip)) {
            $this->error("Please specify a valid IP address or CIDR to unblock.");
            return 1;
        }

        $waf->unblockIp($ip);
        $this->info("Successfully unblocked IP/CIDR [{$ip}].");
        return 0;
    }
}
