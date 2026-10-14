<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/FeatureListCommand.php
| Module    : Console\Commands
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : Command to list all registered Feature Flags and rollout statuses.
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
 * Class FeatureListCommand
 *
 * CLI command to inspect feature flag statuses and targeting rules.
 *
 * @package NetFusion\Core\Console\Commands
 */
class FeatureListCommand extends Command
{
    protected string $name = 'feature:list';
    protected string $description = 'List all configured feature flags, statuses, and rollout percentages';

    public function handle(): int
    {
        /** @var FeatureManager $features */
        $features = $this->app->has('features')
            ? $this->app->make('features')
            : new FeatureManager();

        $all = $features->all();

        if (empty($all)) {
            $this->info("No feature flags configured yet.");
            return 0;
        }

        $this->line();
        $this->info("================================================================================");
        $this->info("   NetFusion Feature Flags & Rollout Management                                  ");
        $this->info("================================================================================");
        $this->line();
        $this->line(sprintf("%-25s | %-12s | %-12s | %s", 'Feature Name', 'Status', 'Rollout %', 'Target Rules'));
        $this->line(str_repeat('-', 80));

        foreach ($all as $name => $meta) {
            $status = strtoupper($meta['status'] ?? 'UNKNOWN');
            $percentage = isset($meta['percentage']) && $meta['percentage'] !== null ? $meta['percentage'] . '%' : '100% (All)';
            $rules = [];

            if (! empty($meta['rules']['users'])) {
                $rules[] = 'Users: [' . implode(', ', (array) $meta['rules']['users']) . ']';
            }

            $rulesStr = empty($rules) ? 'Global' : implode('; ', $rules);

            $this->line(sprintf(
                "%-25s | %-12s | %-12s | %s",
                substr($name, 0, 25),
                $status,
                $percentage,
                $rulesStr
            ));
        }

        $this->line(str_repeat('-', 80));
        $this->success("Total configured feature flags: " . count($all));
        $this->line();

        return 0;
    }
}
