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

class VaultGetCommand extends Command
{
    protected string $signature = 'vault:get {path : Secret path to retrieve} {--ver= : Specific secret version}';
    protected string $description = 'Retrieve and decrypt a secret from the zero-trust Vault';

    public function handle(): int
    {
        /** @var VaultManager $vault */
        $vault = function_exists('app') && app()->has('vault') ? app('vault') : new VaultManager();

        $path = (string)$this->argument('path');
        $ver = $this->option('ver') ? (int)$this->option('ver') : null;

        $secret = $vault->getSecret($path, $ver);
        if (!$secret) {
            $this->error("Secret at path '{$path}' not found.");
            return 1;
        }

        $val = $vault->get($path, $ver);

        $this->info("=== Vault Secret: {$path} ===");
        $this->line("Secret Version: " . $secret->version);
        $this->line("KEK Version   : " . $secret->payload->version);
        $this->line("Cipher        : " . $secret->payload->cipher);
        $this->line("Updated At    : " . $secret->updatedAt);

        $this->info("\n--- Decrypted Value ---");
        $this->line(is_array($val) ? json_encode($val, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : (string)$val);

        return 0;
    }
}
