<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/MakeNotificationCommand.php
| Module    : Core\Console\Commands
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : Console generator scaffolding new domain Notification classes.
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

/**
 * NetFusion Make Notification Command
 *
 * Generates a new Notification class inside a module or the main app.
 * Usage:
 *   php netfusion make:notification InvoicePaid --module=Billing
 *   php netfusion make:notification UserRegistered
 *
 * @package NetFusion\Core\Console\Commands
 * @version 1.0.0
 */
class MakeNotificationCommand extends Command
{
    protected string $name = 'make:notification';
    protected string $description = 'Create a new multi-channel notification class (--module=[name])';

    public function handle(): int
    {
        $name = $this->argument(0);

        if (!$name) {
            $this->error('Notification name is required. Example: php netfusion make:notification InvoicePaid --module=Billing');
            return 1;
        }

        $name = ucfirst(trim($name));
        $module = $this->option('module');

        if ($module) {
            $module    = ucfirst((string) $module);
            $targetDir = $this->app->modulesPath($module . DIRECTORY_SEPARATOR . 'Notifications');
            $namespace = "NetFusion\\Modules\\{$module}\\Notifications";
            $modLabel  = "Modules\\{$module}";
            $relPath   = "app/Modules/{$module}/Notifications/{$name}.php";
        } else {
            $targetDir = $this->app->appPath('Notifications');
            $namespace = "NetFusion\\App\\Notifications";
            $modLabel  = "App";
            $relPath   = "app/Notifications/{$name}.php";
        }

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $filePath = $targetDir . DIRECTORY_SEPARATOR . $name . '.php';

        if (file_exists($filePath)) {
            $this->error("Notification [{$name}] already exists at {$filePath}.");
            return 1;
        }

        $content = $this->stub($name, $namespace, $modLabel, $relPath);
        file_put_contents($filePath, $content);

        $this->success("✔ Notification [{$name}] created successfully!");
        $this->line("  File: {$filePath}");

        return 0;
    }

    protected function stub(string $name, string $namespace, string $modLabel, string $relPath): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : {$relPath}
| Module    : {$modLabel}
| Namespace : {$namespace}
| Purpose   : Multi-channel Notification for {$name}.
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

namespace {$namespace};

use NetFusion\Core\Mail\Message;
use NetFusion\Core\Notifications\Notification;

class {$name} extends Notification
{
    /**
     * Get the notification's delivery channels.
     *
     * @return array<string>
     */
    public function via(object \$notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object \$notifiable): Message
    {
        \$msg = new Message();
        \$msg->subject('Notification: {$name}')
            ->html('<p>You have received a new notification from NetFusion Framework.</p>');

        return \$msg;
    }

    /**
     * Get the array representation of the notification for database storage.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object \$notifiable): array
    {
        return [
            'title'   => '{$name}',
            'message' => 'You have received a new notification.',
            'sent_at' => date('Y-m-d H:i:s'),
        ];
    }
}

PHP;
    }
}
