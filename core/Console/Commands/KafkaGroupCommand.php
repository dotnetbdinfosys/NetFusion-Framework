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
 * Class KafkaGroupCommand
 *
 * CLI: php netfusion kafka:group <list|describe> [group_id]
 */
class KafkaGroupCommand extends Command
{
    protected string $name = 'kafka:group';
    protected string $description = 'Inspect and manage Kafka consumer groups and committed partition offsets';

    public function handle(array $args = []): int
    {
        $action = 'list';
        $groupId = null;

        $positional = [];
        for ($i = 0; $i < count($args); $i++) {
            $arg = (string) $args[$i];
            if (!str_starts_with($arg, '-')) {
                $positional[] = $arg;
            }
        }

        if (empty($positional) && !empty($this->arguments)) {
            $positional = $this->arguments;
        }

        if (!empty($positional)) {
            $action = strtolower($positional[0]);
            $groupId = $positional[1] ?? null;
        }

        $kafka = function_exists('kafka') ? kafka() : $this->app->make(\NetFusion\Core\Kafka\KafkaManager::class);
        $coordinator = $kafka->getCoordinator();

        switch ($action) {
            case 'describe':
                if ($groupId === null) {
                    $this->error('Usage: php netfusion kafka:group describe <group_id>');
                    return 1;
                }
                $group = $coordinator->getGroup($groupId);
                if ($group === null) {
                    $this->error("Consumer group '{$groupId}' not found.");
                    return 1;
                }

                $this->info("Group: {$group->groupId}");
                $this->line("State: {$group->getState()->value}");
                $this->line("Generation ID: {$group->getGenerationId()}");
                $this->line("Leader: " . ($group->getLeaderId() ?? '(none)'));
                $this->line("Assignor: {$group->getAssignor()->name()}");
                $this->line("Members (" . count($group->getMembers()) . "):");
                foreach ($group->getMembers() as $memberId => $meta) {
                    $assigned = implode(', ', array_map(fn($tp) => $tp->toString(), $meta->getAssignedPartitions()));
                    $this->line("  - {$memberId} => [{$assigned}]");
                }
                $this->line("Committed Offsets:");
                foreach ($group->getCommittedOffsets() as $tpStr => $offsetMeta) {
                    $this->line("  - {$tpStr} => offset {$offsetMeta->offset}");
                }
                return 0;

            case 'list':
            default:
                $groups = $coordinator->allGroups();
                if (empty($groups)) {
                    $this->line('No active consumer groups found.');
                    return 0;
                }

                $this->line(sprintf('%-30s %-15s %-10s %-10s', 'GROUP ID', 'STATE', 'MEMBERS', 'GEN ID'));
                $this->line(str_repeat('-', 70));
                foreach ($groups as $id => $group) {
                    $this->line(sprintf(
                        '%-30s %-15s %-10d %-10d',
                        $group->groupId,
                        $group->getState()->value,
                        count($group->getMembers()),
                        $group->getGenerationId()
                    ));
                }
                return 0;
        }
    }
}
