<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/TokenRevokeCommand.php
| Module    : Console\Commands
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : Command to revoke a Personal Access Token or all tokens for an entity.
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
 * Class TokenRevokeCommand
 *
 * CLI command to revoke one or all Personal Access Tokens.
 * Usage:
 *   php netfusion token:revoke 5
 *   php netfusion token:revoke --all --user=1 [--type=App\Models\User]
 *
 * @package NetFusion\Core\Console\Commands
 */
class TokenRevokeCommand extends Command
{
    protected string $name = 'token:revoke';
    protected string $description = 'Revoke a Personal Access Token by ID or all tokens for a user';

    public function handle(): int
    {
        /** @var TokenManager $manager */
        $manager = $this->app->has('token.manager')
            ? $this->app->make('token.manager')
            : new TokenManager();

        $revokeAll = $this->hasOption('all');
        $userId = $this->option('user');
        $tokenableType = (string) ($this->option('type') ?: 'NetFusion\\App\\Models\\User');

        if ($revokeAll) {
            if ($userId === null || $userId === '') {
                $this->error('Error: To revoke all tokens, specify the entity ID with --user=<id>.');
                return 1;
            }

            $count = $manager->revokeAllFor($tokenableType, $userId);
            $this->success("Successfully revoked {$count} token(s) for {$tokenableType} ID [{$userId}].");
            return 0;
        }

        $tokenId = $this->argument(0);
        if ($tokenId === null || $tokenId === '') {
            $this->error('Error: Please provide a token ID to revoke or use --all --user=<id>.');
            $this->line('Usage: php netfusion token:revoke <token_id>');
            $this->line('       php netfusion token:revoke --all --user=<user_id>');
            return 1;
        }

        $deleted = $manager->revokeToken($tokenId);

        if ($deleted) {
            $this->success("Personal Access Token [{$tokenId}] revoked successfully.");
            return 0;
        }

        $this->warn("No token found with ID [{$tokenId}], or it was already revoked.");
        return 0;
    }
}
