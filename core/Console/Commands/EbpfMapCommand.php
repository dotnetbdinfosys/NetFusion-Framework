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
use NetFusion\Core\Network\Ebpf\EbpfManager;

/**
 * eBPF Shared Map Inspection CLI Command.
 */
class EbpfMapCommand extends Command
{
    protected string $name = 'ebpf:map';
    protected string $description = 'Inspect and modify eBPF shared memory maps';

    public function handle(): int
    {
        $mapName = $this->argument(0, 'ip_stats');
        $this->info("Inspecting eBPF shared map: {$mapName}...");

        $mgr = $this->app->has('ebpf') ? $this->app->get('ebpf') : new EbpfManager();
        $map = $mgr->createArrayMap($mapName, 10);
        $map->increment(1, 42);

        $this->line("Map Type: " . $map->getType()->value);
        $this->line("Max Entries: " . $map->getMaxEntries());
        $this->line("Current Entries: " . json_encode(array_slice($map->all(), 0, 5)));

        return 0;
    }
}
