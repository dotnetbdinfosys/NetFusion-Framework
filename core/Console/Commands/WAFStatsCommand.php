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

class WAFStatsCommand extends Command
{
    protected string $name = 'waf:stats';
    protected string $signature = 'waf:stats';
    protected string $description = 'Display Web Application Firewall (WAF) security metrics and active IP blocks';

    public function handle(): int
    {
        /** @var WAFManager $waf */
        $waf = function_exists('waf') ? waf() : (function_exists('app') && app()->has('waf') ? app('waf') : new WAFManager());

        $metrics = $waf->getStore()->getMetrics();
        $activeBlocks = $waf->getStore()->getActiveIpBlocks();

        $this->info("==========================================================================================");
        $this->info("                 Web Application Firewall (WAF) & L7 DDoS Defense Metrics");
        $this->info("==========================================================================================");

        $this->line("");
        $this->info("Traffic Inspection Summary:");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Requests Inspected', (string)($metrics['inspected'] ?? 0)],
                ['Allowed Requests', (string)($metrics['passed'] ?? 0)],
                ['Blocked Requests (403)', (string)($metrics['blocked'] ?? 0)],
                ['Security Challenged Requests', (string)($metrics['challenged'] ?? 0)],
                ['Active Blocked IPs / CIDRs', (string)count($activeBlocks)],
                ['Active Virtual Patches', (string)$waf->getPatchEngine()->count()],
            ]
        );

        if (!empty($activeBlocks)) {
            $this->line("");
            $this->info("Active Quarantined / Blocked IPs:");
            $blockTable = [];
            foreach ($activeBlocks as $ip => $item) {
                $expiresIn = $item['expires_at'] > 0 ? (string)max(0, $item['expires_at'] - time()) . 's' : 'Permanent';
                $blockTable[] = [$ip, $item['reason'], $expiresIn];
            }
            $this->table(['IP Address / CIDR', 'Block Reason', 'Expires In'], $blockTable);
        }

        return 0;
    }
}
