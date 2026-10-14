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

class ZkpProveCommand extends Command
{
    protected string $name = 'zkp:prove';
    protected string $description = 'Generate a zero-knowledge succinct proof from a private witness assignment';

    public function handle(): int
    {
        /** @var ZkpManager $manager */
        $manager = $this->app->has('zkp') ? $this->app->make('zkp') : new ZkpManager();

        $gadgetType = (string)($this->argument(0) ?? 'factor');
        $this->info("Generating zero-knowledge proof for gadget [<comment>{$gadgetType}</comment>]...");

        $circuit = $manager->gadget($gadgetType, "circ_{$gadgetType}");
        $keys = $manager->setup($circuit);

        // Demo witness based on gadget
        $witness = match ($gadgetType) {
            'cubic'  => ['x' => 3, 'out' => 35, 'sym_1' => 9, 'sym_2' => 27],
            'range8' => ['val' => 25, 'b_0' => 1, 'b_1' => 0, 'b_2' => 0, 'b_3' => 1, 'b_4' => 1, 'b_5' => 0, 'b_6' => 0, 'b_7' => 0],
            default  => ['a' => 7, 'b' => 11, 'n' => 77], // factor multiplier
        };

        $proof = $manager->prove($circuit, $witness, $keys['pk']);

        $this->info("Proof Generated Successfully!");
        $this->line("Protocol: <comment>{$proof->getProtocol()}</comment>");
        $this->line("Pi_A: <info>{$proof->getPiA()}</info>");
        $this->line("Pi_B: <info>{$proof->getPiB()}</info>");
        $this->line("Pi_C: <info>{$proof->getPiC()}</info>");
        $this->line("Public Inputs: <comment>" . json_encode($proof->getPublicInputs()) . "</comment>");

        return 0;
    }
}
