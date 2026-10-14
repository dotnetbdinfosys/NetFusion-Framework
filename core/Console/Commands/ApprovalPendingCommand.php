<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/ApprovalPendingCommand.php
| Module    : Workflow
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : CLI command to list all pending approval requests.
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
use NetFusion\Core\Workflow\ApprovalPipeline;

class ApprovalPendingCommand extends Command
{
    protected string $name        = 'approval:pending';
    protected string $description = 'List all pending approval requests in the pipeline.';

    public function handle(): int
    {
        $userId = $this->option('user') ? (int)$this->option('user') : null;

        $requests = ApprovalPipeline::getPendingRequestsForUser($userId);

        $this->info('');
        $this->info('=== Pending Approval Requests ===');
        $this->info('');

        if (empty($requests)) {
            $this->info('No pending approval requests found.');
            return 0;
        }

        $header = sprintf(
            ' %-4s | %-15s | %-6s | %-18s | %-12s | %-10s | %-19s',
            'ID', 'Entity', 'Ref #', 'Pipeline', 'Stage', 'Approver', 'Created At'
        );
        $this->info($header);
        $this->info(str_repeat('-', strlen($header)));

        foreach ($requests as $r) {
            $this->info(sprintf(
                ' %-4s | %-15s | %-6s | %-18s | %-12s | %-10s | %-19s',
                $r['id'],
                $r['entity_type'],
                $r['entity_id'],
                $r['pipeline_name'],
                $r['stage_name'],
                $r['approver_id'] ? "User #{$r['approver_id']}" : 'Anyone',
                $r['created_at']
            ));
        }

        $this->info('');
        $this->info(sprintf("Total pending: %d", count($requests)));
        $this->info('');

        return 0;
    }
}