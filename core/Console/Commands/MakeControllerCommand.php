<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/MakeControllerCommand.php
| Module    : Core\Console\Commands
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : CLI generator command for creating HMVC or Application controllers.
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
 * NetFusion Make Controller Command
 *
 * Generates an HTTP controller inside a specific HMVC module or the main application.
 *
 * @package NetFusion\Core\Console\Commands
 * @version 1.0.0
 */
class MakeControllerCommand extends Command
{
    protected string $name = 'make:controller';
    protected string $description = 'Create a new controller class (--module=[name])';

    public function handle(): int
    {
        $name = $this->argument(0);

        if (!$name) {
            $this->error('Controller name is required. Example: php netfusion make:controller UserController --module=Billing');
            return 1;
        }

        $name = ucfirst(trim($name));
        if (!str_ends_with($name, 'Controller')) {
            $name .= 'Controller';
        }

        $module = $this->option('module');

        if ($module) {
            $module    = ucfirst($module);
            $targetDir = $this->app->modulesPath($module . DIRECTORY_SEPARATOR . 'Controllers');
            $namespace = "NetFusion\\Modules\\{$module}\\Controllers";
            $modLabel  = "Modules\\{$module}";
        } else {
            $targetDir = $this->app->appPath('Controllers');
            $namespace = "NetFusion\\App\\Controllers";
            $modLabel  = "App";
        }

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $filePath = $targetDir . DIRECTORY_SEPARATOR . "{$name}.php";

        if (file_exists($filePath)) {
            $this->error("Controller [{$name}] already exists at {$filePath}.");
            return 1;
        }

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
| Purpose   : Controller handling HTTP requests and application actions.
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

use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;

class {$name}
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request \$request): Response
    {
        return Response::json([
            'status'  => 'success',
            'message' => 'Hello from {$name}::index()',
        ]);
    }
}
PHP;

        file_put_contents($filePath, $template);

        $this->success("Controller [{$name}] created successfully!");
        $this->line("  File: \033[36m{$filePath}\033[0m");

        return 0;
    }
}
