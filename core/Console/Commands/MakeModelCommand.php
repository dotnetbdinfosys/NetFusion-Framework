<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/MakeModelCommand.php
| Module    : Core\Console\Commands
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : CLI generator command for creating Active Record database models.
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
 * NetFusion Make Model Command
 *
 * Generates an Active Record Model class inside a specific HMVC module or the main application.
 *
 * @package NetFusion\Core\Console\Commands
 * @version 1.0.0
 */
class MakeModelCommand extends Command
{
    protected string $name = 'make:model';
    protected string $description = 'Create a new Active Record Model class (--module=[name])';

    public function handle(): int
    {
        $name = $this->argument(0);

        if (!$name) {
            $this->error('Model name is required. Example: php netfusion make:model Invoice --module=Billing');
            return 1;
        }

        $name   = ucfirst(trim($name));
        $module = $this->option('module');

        if ($module) {
            $module    = ucfirst($module);
            $targetDir = $this->app->modulesPath($module . DIRECTORY_SEPARATOR . 'Models');
            $namespace = "NetFusion\\Modules\\{$module}\\Models";
            $modLabel  = "Modules\\{$module}";
        } else {
            $targetDir = $this->app->appPath('Models');
            $namespace = "NetFusion\\App\\Models";
            $modLabel  = "App";
        }

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $filePath = $targetDir . DIRECTORY_SEPARATOR . "{$name}.php";

        if (file_exists($filePath)) {
            $this->error("Model [{$name}] already exists at {$filePath}.");
            return 1;
        }

        $table = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name)) . 's';
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
| Namespace : {$namespace}
| Purpose   : Active Record Model representing the {$table} database table.
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

use NetFusion\Core\Database\Model;

class {$name} extends Model
{
    /**
     * The table associated with the model.
     */
    protected string \$table = '{$table}';

    /**
     * Primary key column.
     */
    protected string \$primaryKey = 'id';

    /**
     * Mass assignable attributes.
     *
     * @var string[]
     */
    protected array \$fillable = [
        // 'name', 'status', 'amount'
    ];

    /**
     * Hidden attributes excluded from arrays/JSON.
     *
     * @var string[]
     */
    protected array \$hidden = [];

    /**
     * Auto-manage timestamps (created_at, updated_at).
     */
    protected bool \$timestamps = true;
}
PHP;

        file_put_contents($filePath, $template);

        $this->success("Model [{$name}] created successfully!");
        $this->line("  File:  \033[36m{$filePath}\033[0m");
        $this->line("  Table: \033[32m{$table}\033[0m");

        return 0;
    }
}
