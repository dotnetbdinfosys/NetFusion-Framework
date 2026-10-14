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

class ZkpVerifyCommand extends Command
{
    protected string $name = 'zkp:verify';
    protected string $description = 'Verify zero-knowledge proof validity against circuit public inputs and verifying key';

    public function handle(): int
    {
        /** @var ZkpManager $manager */
        $manager = $this->app->has('zkp') ? $this->app->make('zkp') : new ZkpManager();

        $gadgetType = (string)($this->argument(0) ?? 'factor');
        $this->info("Verifying ZKP proof pipeline for [<comment>{$gadgetType}</comment>]...");

        $circuit = $manager->gadget($gadgetType, "verify_circ_{$gadgetType}");
        $keys = $manager->setup($circuit);

        $witness = match ($gadgetType) {
            'cubic'  => ['x' => 3, 'out' => 35, 'sym_1' => 9, 'sym_2' => 27],
            'range8' => ['val' => 25, 'b_0' => 1, 'b_1' => 0, 'b_2' => 0, 'b_3' => 1, 'b_4' => 1, 'b_5' => 0, 'b_6' => 0, 'b_7' => 0],
            default  => ['a' => 7, 'b' => 11, 'n' => 77],
        };

        $proof = $manager->prove($circuit, $witness, $keys['pk']);
        $valid = $manager->verify($keys['vk'], $proof);

        if ($valid) {
            $this->info("✔ Proof Verification Succeeded: Statement is valid and sound without revealing private witness.");
            return 0;
        }

        $this->error("✖ Proof Verification Failed!");
        return 1;
    }
}
