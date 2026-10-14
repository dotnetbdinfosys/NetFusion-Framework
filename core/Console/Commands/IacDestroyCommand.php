<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
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

declare(strict_types=1);

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;
use NetFusion\Core\IaC\IacManager;

/**
 * Class IacDestroyCommand
 *
 * CLI: php netfusion iac:destroy
 */
class IacDestroyCommand extends Command
{
    protected string $name = 'iac:destroy';
    protected string $description = 'Destroy all remote managed infrastructure tracked in the state store';

    public function handle(array $args = []): int
    {
        /** @var IacManager $iac */
        $iac = app(IacManager::class);

        $this->warn("Destroying all tracked infrastructure...");
        $result = $iac->destroy();

        foreach ($result['logs'] as $log) {
            $this->line($log);
        }

        $this->info("Destroy complete! Resources destroyed: {$result['applied']}.");
        return $result['success'] ? 0 : 1;
    }
}
