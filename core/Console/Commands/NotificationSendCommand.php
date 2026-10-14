<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/NotificationSendCommand.php
| Module    : Console\Commands
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : CLI command to test and dispatch a notification to an entity.
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
use NetFusion\Core\Notifications\Notification;
use NetFusion\Core\Notifications\NotificationManager;

/**
 * Class NotificationSendCommand
 *
 * Command: php netfusion notification:send <ModelClass> <id> <NotificationClass>
 *
 * @package NetFusion\Core\Console\Commands
 */
class NotificationSendCommand extends Command
{
    protected string $name = 'notification:send';
    protected string $description = 'Dispatch a notification to a specific notifiable model entity';

    public function handle(): int
    {
        $modelName = $this->argument(0);
        $id = $this->argument(1);
        $notifClass = $this->argument(2);

        if (empty($modelName) || empty($id) || empty($notifClass)) {
            $this->error("Usage: php netfusion notification:send <ModelClass> <id> <NotificationClass>");
            return 1;
        }

        $resolvedModel = $this->resolveClass($modelName, ['App\\Models\\', 'NetFusion\\App\\Models\\']);
        if ($resolvedModel === null) {
            $this->error("Model class [{$modelName}] not found.");
            return 1;
        }

        $resolvedNotif = $this->resolveClass($notifClass, ['App\\Notifications\\', 'NetFusion\\App\\Notifications\\']);
        if ($resolvedNotif === null) {
            $this->error("Notification class [{$notifClass}] not found.");
            return 1;
        }

        if (! method_exists($resolvedModel, 'find')) {
            $this->error("Model [{$resolvedModel}] must provide a find() method.");
            return 1;
        }

        $entity = $resolvedModel::find($id);
        if ($entity === null) {
            $this->error("Record [{$id}] not found in [{$resolvedModel}].");
            return 1;
        }

        /** @var Notification $notification */
        $notification = new $resolvedNotif();

        /** @var NotificationManager $manager */
        $manager = $this->app->has(NotificationManager::class)
            ? $this->app->make(NotificationManager::class)
            : new NotificationManager($this->app);

        $this->info("Dispatching [{$resolvedNotif}] to [{$resolvedModel} #{$id}]...");
        $manager->send($entity, $notification);

        $this->info("✔ Notification [{$notification->id}] successfully dispatched.");
        return 0;
    }

    /**
     * Resolve class by testing candidates.
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
