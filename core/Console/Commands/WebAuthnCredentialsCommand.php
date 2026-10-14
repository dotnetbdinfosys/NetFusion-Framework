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

use NetFusion\Core\Auth\WebAuthn\WebAuthnManager;
use NetFusion\Core\Console\Command;

class WebAuthnCredentialsCommand extends Command
{
    protected string $name = 'webauthn:credentials';
    protected string $description = 'List or delete WebAuthn / Passkey biometric credentials';

    public function handle(): int
    {
        /** @var WebAuthnManager $manager */
        $manager = $this->app->has('webauthn') ? $this->app->make('webauthn') : new WebAuthnManager();
        $store = $manager->getStore();

        $action = $this->argument(0) ?? 'list';

        if ($action === 'delete') {
            $id = $this->option('id') ?? $this->argument(1);
            if (!$id) {
                $this->error("Credential ID is required for delete action. Usage: php netfusion webauthn:credentials delete <id>");
                return 1;
            }
            $deleted = $store->delete((string)$id);
            if ($deleted) {
                $this->info("Passkey credential [{$id}] successfully removed.");
            } else {
                $this->error("Failed to remove passkey credential [{$id}]. Not found.");
            }
            return 0;
        }

        $userId = $this->option('user');
        if ($userId) {
            $creds = $store->findByUserId($userId);
            $this->info("WebAuthn Passkey credentials for User ID [{$userId}]:");
        } else {
            if (method_exists($store, 'all')) {
                $creds = $store->all();
            } else {
                // PDO query via DB store
                $creds = $store->findByUserId('%');
            }
            $this->info("All WebAuthn Passkey credentials:");
        }

        if (empty($creds)) {
            $this->comment("No WebAuthn passkey credentials found.");
            return 0;
        }

        $this->line(str_repeat('-', 90));
        $this->line(sprintf("%-28s | %-8s | %-16s | %-6s | %-8s", "Credential ID", "User ID", "Name", "Algo", "Counter"));
        $this->line(str_repeat('-', 90));

        foreach ($creds as $cred) {
            $idShort = strlen($cred->id) > 26 ? substr($cred->id, 0, 24) . '..' : $cred->id;
            $this->line(sprintf(
                "%-28s | %-8s | %-16s | %-6s | %-8d",
                $idShort,
                (string)$cred->userId,
                $cred->name ?? 'Passkey',
                $cred->coseAlgorithm ?? 'ES256',
                $cred->signCount
            ));
        }

        $this->line(str_repeat('-', 90));
        return 0;
    }
}
