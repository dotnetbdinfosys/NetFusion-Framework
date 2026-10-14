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
use NetFusion\Core\ZKP\ZkpManager;

class ZkpSetupCommand extends Command
{
    protected string $name = 'zkp:setup';
    protected string $description = 'Perform trusted setup and generate proving/verifying keys for a circuit gadget';

    public function handle(): int
    {
        /** @var ZkpManager $manager */
        $manager = $this->app->has('zkp') ? $this->app->make('zkp') : new ZkpManager();

        $gadgetType = (string)($this->argument(0) ?? 'factor');
        $circuitName = (string)($this->argument(1) ?? "circuit_{$gadgetType}");

        $this->info("Initializing circuit gadget [<comment>{$gadgetType}</comment>] for setup...");
        $circuit = $manager->gadget($gadgetType, $circuitName);

        $keys = $manager->setup($circuit);

        $this->info("ZKP Setup Complete!");
        $this->line("Circuit: <comment>{$circuitName}</comment>");
        $this->line("Constraints Count: <info>" . count($circuit->getConstraints()) . "</info>");
        $this->line("Public Signals: <info>" . implode(', ', $keys['vk']->getPublicSignals()) . "</info>");
        $this->line("Proving Key Fingerprint: <comment>" . $keys['pk']->getData()['fingerprint'] . "</comment>");

        return 0;
    }
}
