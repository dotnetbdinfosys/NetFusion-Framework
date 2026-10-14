<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/FeatureDisableCommand.php
| Module    : Console\Commands
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : Command to deactivate a Feature Flag globally or for specific users.
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

use NetFusion\Core\Console\Command;
use NetFusion\Core\Features\FeatureManager;

/**
 * Class FeatureDisableCommand
 *
 * CLI command to deactivate a feature toggle.
 *
 * @package NetFusion\Core\Console\Commands
 */
class FeatureDisableCommand extends Command
{
    protected string $name = 'feature:disable';
    protected string $description = 'Deactivate a feature flag globally or for specific users';

    public function handle(): int
    {
        $feature = $this->argument(0);

        if ($feature === null || $feature === '') {
            $this->error("Error: Please provide the feature flag name as first argument.");
            $this->line("Usage: php netfusion feature:disable <name> [--users=1,2,3]");
            return 1;
        }

        /** @var FeatureManager $features */
        $features = $this->app->has('features')
            ? $this->app->make('features')
            : new FeatureManager();

        $usersOption = $this->option('users');

        if ($usersOption !== null && $usersOption !== '') {
            $users = array_map('trim', explode(',', (string) $usersOption));
            $features->deactivateFor($feature, $users);
            $this->success("Feature [{$feature}] deactivated for users: [" . implode(', ', $users) . "]");
            return 0;
        }

        $features->deactivate($feature);
        $this->success("Feature [{$feature}] deactivated globally.");
        return 0;
    }
}
