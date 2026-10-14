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

/**
 * Class AccountingReportCommand
 *
 * CLI: php netfusion accounting:report [--type=trial|pl|balance] [--from=YYYY-MM-DD] [--to=YYYY-MM-DD]
 *
 * Generates financial reports using the AccountingManager singleton.
 */
class AccountingReportCommand extends Command
{
    protected string $name        = 'accounting:report';
    protected string $description = 'Generate financial reports: trial balance, P&L, or balance sheet';

    public function handle(array $args = []): int
    {
        $type = $this->getOption($args, 'type', 'trial');
        $from = $this->getOption($args, 'from', date('Y-01-01'));
        $to   = $this->getOption($args, 'to',   date('Y-m-d'));

        try {
            $accounting = accounting();
        } catch (\Throwable $e) {
            $this->error("Accounting engine unavailable: " . $e->getMessage());
            return 1;
        }

        switch ($type) {
            case 'trial':
                echo $accounting->trialBalance($to)->render();
                break;

            case 'pl':
            case 'income':
                echo $accounting->incomeStatement($from, $to)->render();
                break;

            case 'balance':
                echo $accounting->balanceSheet($to)->render();
                break;

            default:
                $this->error("Unknown report type '{$type}'. Use: trial | pl | balance");
                return 1;
        }

        return 0;
    }

    private function getOption(array $args, string $key, string $default = ''): string
    {
        foreach ($args as $arg) {
            if (str_starts_with($arg, "--{$key}=")) {
                return substr($arg, strlen("--{$key}="));
            }
        }
        return $default;
    }
}
