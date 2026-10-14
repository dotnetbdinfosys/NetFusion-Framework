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
 * Class IacApplyCommand
 *
 * CLI: php netfusion iac:apply [file.tf] [--auto-approve]
 */
class IacApplyCommand extends Command
{
    protected string $name = 'iac:apply';
    protected string $description = 'Builds or changes multi-cloud infrastructure according to configuration files';

    public function handle(array $args = []): int
    {
        $filePath = null;
        foreach ($args as $arg) {
            if (is_string($arg) && !str_starts_with($arg, '--')) {
                $filePath = $arg;
                break;
            }
        }

        $hcl = ($filePath !== null && file_exists($filePath))
            ? (string) file_get_contents($filePath)
            : 'resource "aws_vpc" "app" { cidr_block = "10.0.0.0/16" }';

        /** @var IacManager $iac */
        $iac = app(IacManager::class);

        $this->info("Applying infrastructure changes...");
        $result = $iac->apply($hcl);

        foreach ($result['logs'] as $log) {
            $this->line($log);
        }

        $this->info("Apply complete! Resources: {$result['applied']} added/updated, {$result['failed']} failed.");
        return $result['success'] ? 0 : 1;
    }
}
