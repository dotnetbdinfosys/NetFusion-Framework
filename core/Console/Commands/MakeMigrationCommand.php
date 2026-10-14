<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/MakeMigrationCommand.php
| Module    : Core\Console\Commands
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : CLI generator command for creating timestamped database migration files.
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
 * NetFusion Make Migration Command
 *
 * Generates a timestamped database migration file inside a module or the app.
 *
 * @package NetFusion\Core\Console\Commands
 * @version 1.0.0
 */
class MakeMigrationCommand extends Command
{
    protected string $name = 'make:migration';
    protected string $description = 'Create a new migration file (--module=[name])';

    public function handle(): int
    {
        $name = $this->argument(0);

        if (!$name) {
            $this->error('Migration name is required. Example: php netfusion make:migration create_orders_table --module=Billing');
            return 1;
        }

        $name = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $name));
        $module = $this->option('module');

        if ($module) {
            $module    = ucfirst($module);
            $targetDir = $this->app->modulesPath($module . DIRECTORY_SEPARATOR . 'Database' . DIRECTORY_SEPARATOR . 'Migrations');
            $modLabel  = "Modules\\{$module}";
        } else {
            $targetDir = $this->app->storagePath('migrations');
            $modLabel  = "Storage\Migrations";
        }

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $timestamp = date('Y_m_d_His');
        $fileName  = "{$timestamp}_{$name}.php";
        $filePath  = $targetDir . DIRECTORY_SEPARATOR . $fileName;

        $relFile = str_replace([$this->app->basePath() . DIRECTORY_SEPARATOR, '\\'], ['', '/'], $filePath);

        $template = <<<PHP
<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : {$relFile}
| Module    : {$modLabel}
| Namespace : None (Migration File)
| Purpose   : Migration script for {$name}.
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

use NetFusion\Core\Database\Connection;
use NetFusion\Core\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(Connection \$db): void
    {
        // \$db->statement("CREATE TABLE example (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(255))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(Connection \$db): void
    {
        // \$db->statement("DROP TABLE IF EXISTS example");
    }
};
PHP;

        file_put_contents($filePath, $template);

        $this->success("Created Migration: {$fileName}");
        $this->line("  File: \033[36m{$filePath}\033[0m");

        return 0;
    }
}
