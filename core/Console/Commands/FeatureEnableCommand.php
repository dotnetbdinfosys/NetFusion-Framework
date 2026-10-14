<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/FeatureEnableCommand.php
| Module    : Console\Commands
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : Command to activate a Feature Flag globally, by percentage, or for users.
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
 * Class FeatureEnableCommand
 *
 * CLI command to activate a feature toggle.
 *
 * @package NetFusion\Core\Console\Commands
 */
class FeatureEnableCommand extends Command
{
    protected string $name = 'feature:enable';
    protected string $description = 'Enable a feature flag globally, by percentage, or for specific users';

    public function handle(): int
    {
        $feature = $this->argument(0);

        if ($feature === null || $feature === '') {
            $this->error("Error: Please provide the feature flag name as first argument.");
            $this->line("Usage: php netfusion feature:enable <name> [--percentage=50] [--users=1,2,3]");
            return 1;
        }

        /** @var FeatureManager $features */
        $features = $this->app->has('features')
            ? $this->app->make('features')
            : new FeatureManager();

        $percentage = $this->option('percentage');
        $usersOption = $this->option('users');

        if ($usersOption !== null && $usersOption !== '') {
            $users = array_map('trim', explode(',', (string) $usersOption));
            $features->activateFor($feature, $users);
            $this->success("Feature [{$feature}] activated for users: [" . implode(', ', $users) . "]");
            return 0;
        }

        if ($percentage !== null && is_numeric($percentage)) {
            $pct = (int) $percentage;
            $features->percentage($feature, $pct);
            $this->success("Feature [{$feature}] enabled for canary rollout at {$pct}% of users.");
            return 0;
        }

        $features->activate($feature);
        $this->success("Feature [{$feature}] enabled globally (100%).");
        return 0;
    }
}
