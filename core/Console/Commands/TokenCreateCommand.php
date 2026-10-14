<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/TokenCreateCommand.php
| Module    : Console\Commands
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : Command to create and issue a new Personal Access Token.
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

use DateTimeImmutable;
use NetFusion\Core\Auth\Tokens\TokenManager;
use NetFusion\Core\Console\Command;

/**
 * Class TokenCreateCommand
 *
 * CLI command to issue a Personal Access Token.
 * Usage:
 *   php netfusion token:create 1 --name=mobile-app --scopes=read,write --expires=30
 *
 * @package NetFusion\Core\Console\Commands
 */
class TokenCreateCommand extends Command
{
    protected string $name = 'token:create';
    protected string $description = 'Issue a new Personal Access Token for a user or entity';

    public function handle(): int
    {
        $userId = $this->argument(0);

        if ($userId === null || $userId === '') {
            $this->error('Error: Please provide a valid user/entity ID as the first argument.');
            $this->line('Usage: php netfusion token:create <user_id> [--name=api-token] [--scopes=*] [--expires=30]');
            return 1;
        }

        $name = (string) ($this->option('name') ?: 'default');
        $scopesStr = (string) ($this->option('scopes') ?: '*');
        $abilities = array_filter(array_map('trim', explode(',', $scopesStr)));
        if (empty($abilities)) {
            $abilities = ['*'];
        }

        $tokenableType = (string) ($this->option('type') ?: 'NetFusion\\App\\Models\\User');

        $expiresAt = null;
        $expiresDays = $this->option('expires');
        if ($expiresDays !== null && is_numeric($expiresDays)) {
            $expiresAt = (new DateTimeImmutable())->modify('+' . (int) $expiresDays . ' days');
        }

        /** @var TokenManager $manager */
        $manager = $this->app->has('token.manager')
            ? $this->app->make('token.manager')
            : new TokenManager();

        $tokenResult = $manager->createToken(
            tokenableType: $tokenableType,
            tokenableId  : $userId,
            name         : $name,
            abilities    : $abilities,
            expiresAt    : $expiresAt
        );

        $this->success("Personal Access Token created successfully!");
        $this->line("--------------------------------------------------");
        $this->line("ID        : " . $tokenResult->accessToken->getKey());
        $this->line("Entity    : {$tokenableType} (ID: {$userId})");
        $this->line("Name      : {$name}");
        $this->line("Abilities : " . implode(', ', $abilities));
        $this->line("Expires   : " . ($expiresAt ? $expiresAt->format('Y-m-d H:i:s') : 'Never'));
        $this->line("--------------------------------------------------");
        $this->warn("Plain-Text Token (copy this now, it cannot be retrieved again):");
        $this->info($tokenResult->plainTextToken);
        $this->line("--------------------------------------------------");

        return 0;
    }
}
