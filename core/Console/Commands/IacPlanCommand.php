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
 * Class IacPlanCommand
 *
 * CLI: php netfusion iac:plan [file.tf] [--var=key=val]
 */
class IacPlanCommand extends Command
{
    protected string $name = 'iac:plan';
    protected string $description = 'Generate and show an infrastructure execution plan diff against state';

    public function handle(array $args = []): int
    {
        $filePath = null;
        $variables = [];

        foreach ($args as $arg) {
            if (!is_string($arg)) {
                continue;
            }
            if (str_starts_with($arg, '--var=')) {
                $pair = explode('=', substr($arg, 6), 2);
                if (count($pair) === 2) {
                    $variables[$pair[0]] = $pair[1];
                }
            } elseif (!str_starts_with($arg, '--') && $filePath === null) {
                $filePath = $arg;
            }
        }

        $hcl = '';
        if ($filePath !== null && file_exists($filePath)) {
            $hcl = (string) file_get_contents($filePath);
        } else {
            // Default sample HCL if none provided
            $hcl = <<<'HCL'
resource "aws_vpc" "main" {
  cidr_block = "10.0.0.0/16"
  enable_dns_hostnames = true
}

resource "aws_subnet" "public" {
  vpc_id = aws_vpc.main.id
  cidr_block = "10.0.1.0/24"
}
HCL;
        }

        /** @var IacManager $iac */
        $iac = app(IacManager::class);
        $plan = $iac->plan($hcl, $variables);

        $counts = $plan->getCounts();

        $this->info("=== NetFusion Infrastructure as Code Execution Plan ===");
        $this->line("Plan ID       : " . $plan->planId);
        $this->line("Execution DAG : " . implode(' -> ', $plan->topologicalOrder));
        $this->line("Summary       : {$counts['create']} to add, {$counts['update']} to change, {$counts['destroy']} to destroy, {$counts['noop']} unchanged.");
        $this->line("--------------------------------------------------");

        foreach ($plan->getChanges() as $change) {
            $actionUpper = strtoupper($change->action->value);
            $this->line("[{$actionUpper}] {$change->address}");
        }

        return 0;
    }
}
