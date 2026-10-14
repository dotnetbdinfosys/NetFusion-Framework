<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/NotificationListCommand.php
| Module    : Console\Commands
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : CLI command to inspect in-app notifications for an entity.
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
use NetFusion\Core\Notifications\DatabaseNotification;

/**
 * Class NotificationListCommand
 *
 * Command: php netfusion notification:list <ModelClass> <id> [--unread]
 *
 * @package NetFusion\Core\Console\Commands
 */
class NotificationListCommand extends Command
{
    protected string $name = 'notification:list';
    protected string $description = 'List in-app database notifications for a specific entity';

    public function handle(): int
    {
        $modelName = $this->argument(0);
        $id = $this->argument(1);

        if (empty($modelName) || empty($id)) {
            $this->error("Usage: php netfusion notification:list <ModelClass> <id> [--unread]");
            return 1;
        }

        $onlyUnread = $this->hasOption('unread');

        $resolvedModel = $this->resolveClass($modelName, ['App\\Models\\', 'NetFusion\\App\\Models\\']);
        $type = $resolvedModel ?? $modelName;

        $builder = DatabaseNotification::query()
            ->where('notifiable_type', $type)
            ->where('notifiable_id', (string) $id)
            ->orderBy('created_at', 'DESC');

        if ($onlyUnread) {
            $builder->whereNull('read_at');
        }

        $rows = $builder->get();

        $this->line();
        $this->info("================================================================================");
        $this->info("   Notifications for [{$type} #{$id}]" . ($onlyUnread ? " (Unread Only)" : ""));
        $this->info("   Total Found: " . count($rows));
        $this->info("================================================================================");
        $this->line();

        if (empty($rows)) {
            $this->warn("No notifications found.");
            return 0;
        }

        printf("%-18s | %-28s | %-10s | %-16s\n", "Notification ID", "Type", "Status", "Created At");
        echo str_repeat('-', 78) . PHP_EOL;

        foreach ($rows as $row) {
            $notifId = substr((string) ($row['id'] ?? ''), 0, 16) . '..';
            $notifType = class_basename((string) ($row['type'] ?? ''));
            if (mb_strlen($notifType) > 26) {
                $notifType = mb_substr($notifType, 0, 24) . '..';
            }
            $status = ! empty($row['read_at']) ? 'Read' : 'Unread';
            $createdAt = (string) ($row['created_at'] ?? '');

            printf("%-18s | %-28s | %-10s | %-16s\n", $notifId, $notifType, $status, $createdAt);
        }

        $this->line();
        return 0;
    }

    /**
     * Resolve class name.
     *
     * @param string        $name
     * @param array<string> $prefixes
     * @return class-string|null
     */
    protected function resolveClass(string $name, array $prefixes = []): ?string
    {
        if (class_exists($name)) {
            return $name;
        }

        foreach ($prefixes as $prefix) {
            $candidate = $prefix . $name;
            if (class_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
