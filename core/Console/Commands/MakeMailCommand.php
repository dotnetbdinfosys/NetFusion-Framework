<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/MakeMailCommand.php
| Module    : Core\Console\Commands
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : Console generator scaffolding new Mailable email classes.
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
 * NetFusion Make Mail Command
 *
 * Generates a new Mailable class inside a module or the main app.
 * Usage:
 *   php netfusion make:mail WelcomeEmail --module=Auth
 *   php netfusion make:mail InvoiceReceipt
 *
 * @package NetFusion\Core\Console\Commands
 * @version 1.0.0
 */
class MakeMailCommand extends Command
{
    protected string $name = 'make:mail';
    protected string $description = 'Create a new Mailable email class (--module=[name])';

    public function handle(): int
    {
        $name = $this->argument(0);

        if (!$name) {
            $this->error('Mailable name is required. Example: php netfusion make:mail WelcomeEmail --module=Auth');
            return 1;
        }

        $name = ucfirst(trim($name));
        $module = $this->option('module');

        if ($module) {
            $module    = ucfirst((string) $module);
            $targetDir = $this->app->modulesPath($module . DIRECTORY_SEPARATOR . 'Mail');
            $namespace = "NetFusion\\Modules\\{$module}\\Mail";
            $modLabel  = "Modules\\{$module}";
            $relPath   = "app/Modules/{$module}/Mail/{$name}.php";
        } else {
            $targetDir = $this->app->appPath('Mail');
            $namespace = "NetFusion\\App\\Mail";
            $modLabel  = "App";
            $relPath   = "app/Mail/{$name}.php";
        }

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $filePath = $targetDir . DIRECTORY_SEPARATOR . $name . '.php';

        if (file_exists($filePath)) {
            $this->error("Mailable [{$name}] already exists at {$filePath}.");
            return 1;
        }

        $content = $this->stub($name, $namespace, $modLabel, $relPath);
        file_put_contents($filePath, $content);

        $this->success("✔ Mailable [{$name}] created successfully!");
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
| Purpose   : Mailable email class for {$name}.
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

use NetFusion\Core\Mail\Mailable;

class {$name} extends Mailable
{
    /**
     * Build the message.
     */
    public function build(): static
    {
        return \$this->subject('{$name}')
                     ->html('<p>Hello from NetFusion Framework!</p>');
    }
}

PHP;
    }
}
