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

class WAFBlockCommand extends Command
{
    protected string $name = 'waf:block';
    protected string $signature = 'waf:block {ip : IP address or CIDR subnet to block} {--reason=Manual admin block : Block reason} {--ttl=86400 : TTL in seconds (0 for permanent)}';
    protected string $description = 'Block or quarantine an offending IP address or CIDR subnet';

    public function handle(): int
    {
        /** @var WAFManager $waf */
        $waf = function_exists('waf') ? waf() : (function_exists('app') && app()->has('waf') ? app('waf') : new WAFManager());

        $ip = (string)$this->argument(0);
        if (empty($ip)) {
            $this->error("Please specify a valid IP address or CIDR to block.");
            return 1;
        }

        $reason = (string)$this->option('reason', 'Manual admin block');
        $ttl = (int)$this->option('ttl', 86400);

        $waf->blockIp($ip, $reason, $ttl);
        $duration = $ttl > 0 ? "for {$ttl}s" : 'permanently';

        $this->info("Successfully blocked IP/CIDR [{$ip}] {$duration}. Reason: {$reason}");
        return 0;
    }
}
