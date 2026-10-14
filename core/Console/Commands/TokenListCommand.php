<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/TokenListCommand.php
| Module    : Console\Commands
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : Command to list all active Personal Access Tokens for an entity.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| Website   : www.dotnetbd.com
| Copyright : © DotNetBD. All rights reserved.
| License   : MIT
| Version   : 1.0.0
|
|--------------------------------------------------------------------------
*/

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Auth\Tokens\TokenManager;
use NetFusion\Core\Console\Command;

/**
 * Class TokenListCommand
 *
 * CLI command to list all Personal Access Tokens for a user or entity.
 * Usage:
 *   php netfusion token:list 1 [--type=App\Models\User]
 *
 * @package NetFusion\Core\Console\Commands
 */
class TokenListCommand extends Command
{
    protected string $name = 'token:list';
    protected string $description = 'List all Personal Access Tokens belonging to an entity';

    public function handle(): int
    {
        $userId = $this->argument(0);

        if ($userId === null || $userId === '') {
            $this->error('Error: Please provide a valid user/entity ID as the first argument.');
            $this->line('Usage: php netfusion token:list <user_id> [--type=App\\Models\\User]');
            return 1;
        }

        $tokenableType = (string) ($this->option('type') ?: 'NetFusion\\App\\Models\\User');

        /** @var TokenManager $manager */
        $manager = $this->app->has('token.manager')
            ? $this->app->make('token.manager')
            : new TokenManager();

        $tokens = $manager->getTokensFor($tokenableType, $userId);

        if (empty($tokens)) {
            $this->info("No active personal access tokens found for {$tokenableType} ID [{$userId}].");
            return 0;
        }

        $this->info("Tokens for {$tokenableType} (ID: {$userId}):");
        $this->line(str_repeat('-', 80));
        $this->line(sprintf("%-5s | %-20s | %-20s | %-12s | %-12s", 'ID', 'Name', 'Abilities', 'Last Used', 'Status'));
        $this->line(str_repeat('-', 80));

        foreach ($tokens as $token) {
            $id = $token->getKey();
            $name = (string) $token->getAttribute('name');
            $abilities = implode(', ', $token->getAbilities());
            $lastUsed = $token->getAttribute('last_used_at') ?: 'Never';
            $status = $token->isExpired() ? 'EXPIRED' : 'ACTIVE';

            $this->line(sprintf(
                "%-5s | %-20s | %-20s | %-12s | %-12s",
                (string) $id,
                substr($name, 0, 20),
                substr($abilities, 0, 20),
                substr((string) $lastUsed, 0, 12),
                $status
            ));
        }

        $this->line(str_repeat('-', 80));
        $this->success("Total tokens found: " . count($tokens));

        return 0;
    }
}
