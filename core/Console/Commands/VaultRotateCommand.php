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
use NetFusion\Core\Security\Vault\VaultManager;

class VaultRotateCommand extends Command
{
    protected string $signature = 'vault:rotate {--reencrypt : Re-encrypt / re-wrap all existing secrets under new KEK}';
    protected string $description = 'Rotate Master Key Encryption Key (KEK) and re-wrap stored secrets';

    public function handle(): int
    {
        /** @var VaultManager $vault */
        $vault = function_exists('app') && app()->has('vault') ? app('vault') : new VaultManager();

        $oldVer = $vault->getKeyProvider()->getActiveVersion();
        $newVer = $vault->rotateMasterKey();

        $this->info("=== Master Key (KEK) Rotated Successfully ===");
        $this->line("Previous Version: {$oldVer}");
        $this->line("New Active Version: {$newVer}");

        if ($this->option('reencrypt')) {
            $count = $vault->reencryptAll();
            $this->info("Re-wrapped {$count} stored secret(s) with new KEK version {$newVer}.");
        }

        return 0;
    }
}
