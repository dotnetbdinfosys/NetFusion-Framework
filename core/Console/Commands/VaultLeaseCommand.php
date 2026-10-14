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

class VaultLeaseCommand extends Command
{
    protected string $signature = 'vault:lease {path : Secret template path (e.g. database/app_db)} {--ttl=3600 : Lease TTL in seconds} {--revoke= : Revoke a specific lease ID}';
    protected string $description = 'Issue or revoke ephemeral dynamic secret leases';

    public function handle(): int
    {
        /** @var VaultManager $vault */
        $vault = function_exists('app') && app()->has('vault') ? app('vault') : new VaultManager();

        $revokeId = $this->option('revoke');
        if ($revokeId) {
            $revoked = $vault->revokeLease((string)$revokeId);
            if ($revoked) {
                $this->info("Lease '{$revokeId}' successfully revoked.");
                return 0;
            }
            $this->error("Failed to revoke lease '{$revokeId}'.");
            return 1;
        }

        $path = (string)$this->argument('path');
        $ttl = (int)($this->option('ttl') ?: 3600);

        $lease = $vault->lease($path, $ttl);
        $creds = $vault->getLeaseCredentials($lease->id);

        $this->info("=== Dynamic Ephemeral Lease Issued ===");
        $this->line("Lease ID  : " . $lease->id);
        $this->line("Path      : " . $lease->path);
        $this->line("TTL       : {$lease->ttl}s");
        $this->line("Expires At: " . date('Y-m-d H:i:s', (int)$lease->expiresAt));

        $this->info("\n--- Generated Dynamic Credentials ---");
        $this->line(json_encode($creds, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return 0;
    }
}
