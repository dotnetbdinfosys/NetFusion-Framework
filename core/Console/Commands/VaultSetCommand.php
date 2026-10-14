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

class VaultSetCommand extends Command
{
    protected string $signature = 'vault:set {path : Secret path (e.g. database/production)} {value : Secret value (or JSON)}';
    protected string $description = 'Envelope-encrypt and write a secret into the zero-trust Vault';

    public function handle(): int
    {
        /** @var VaultManager $vault */
        $vault = function_exists('app') && app()->has('vault') ? app('vault') : new VaultManager();

        $path = (string)$this->argument('path');
        $rawVal = (string)$this->argument('value');
        $json = json_decode($rawVal, true);
        $val = is_array($json) ? $json : $rawVal;

        $secret = $vault->set($path, $val);

        $this->info("=== Secret Encrypted & Stored Successfully ===");
        $this->line("Path       : " . $secret->path);
        $this->line("Version    : " . $secret->version);
        $this->line("KEK Version: " . $secret->payload->version);
        $this->line("Cipher     : " . $secret->payload->cipher);
        $this->line("Updated At : " . $secret->updatedAt);

        return 0;
    }
}
