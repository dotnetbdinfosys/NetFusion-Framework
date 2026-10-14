<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/MakeModuleCommand.php
| Module    : Core\Console\Commands
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : HMVC code generator command to create a complete, isolated module skeleton.
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
 * NetFusion Make Module Command
 *
 * Scaffolds an entire plug-and-play HMVC module directory structure with
 * manifest, providers, routes, controllers, and database directories.
 *
 * @package NetFusion\Core\Console\Commands
 * @version 1.0.0
 */
class MakeModuleCommand extends Command
{
    protected string $name = 'make:module';
    protected string $description = 'Create a new plug-and-play HMVC module skeleton';

    public function handle(): int
    {
        $moduleName = $this->argument(0);

        if (!$moduleName) {
            $this->error('Module name is required. Example: php netfusion make:module Accounting');
            return 1;
        }

        $moduleName = ucfirst(trim($moduleName));
        $slug       = strtolower($moduleName);
        $moduleDir  = $this->app->modulesPath($moduleName);

        if (is_dir($moduleDir)) {
            $this->error("Module [{$moduleName}] already exists at {$moduleDir}.");
            return 1;
        }

        $this->info("Creating HMVC Module: {$moduleName}...");

        // 1. Create directory structure
        $subDirs = [
            '',
            'Controllers',
            'Models',
            'Providers',
            'Views',
            'Routes',
            'Database' . DIRECTORY_SEPARATOR . 'Migrations',
            'Database' . DIRECTORY_SEPARATOR . 'Seeders',
        ];

        foreach ($subDirs as $sub) {
            $path = $sub !== '' ? $moduleDir . DIRECTORY_SEPARATOR . $sub : $moduleDir;
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }

        // 2. Generate module.json
        $manifest = [
            'name'         => "{$moduleName} Module",
            'slug'         => $slug,
            'version'      => '1.0.0',
            'description'  => "{$moduleName} business module for NetFusion Framework",
            'author'       => 'Md. Atiquzzaman Ashish (DotNetBD)',
            'enabled'      => true,
            'priority'     => 100,
            'providers'    => [
                "NetFusion\\Modules\\{$moduleName}\\Providers\\{$moduleName}ServiceProvider",
            ],
            'dependencies' => [],
        ];
        file_put_contents(
            $moduleDir . DIRECTORY_SEPARATOR . 'module.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        // 3. Generate config.php
        $configContent = <<<PHP
<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/{$moduleName}/config.php
| Module    : Modules\\{$moduleName}
| Namespace : None (Module Config)
| Purpose   : Configuration file for {$moduleName} Module.
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

return [
    'enabled' => true,
    'default_setting' => 'standard',
];
PHP;
        file_put_contents($moduleDir . DIRECTORY_SEPARATOR . 'config.php', $configContent);

        // 4. Generate ServiceProvider
        $providerContent = <<<PHP
<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/{$moduleName}/Providers/{$moduleName}ServiceProvider.php
| Module    : Modules\\{$moduleName}
| Namespace : NetFusion\Modules\\{$moduleName}\Providers
| Purpose   : Service provider managing lifecycle, dependencies, and boot hooks for {$moduleName} Module.
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

namespace NetFusion\Modules\\{$moduleName}\Providers;

use NetFusion\Core\Application;
use NetFusion\Core\Modules\ModuleServiceProvider;

class {$moduleName}ServiceProvider extends ModuleServiceProvider
{
    public function register(Application \$app): void
    {
        // Bind module services here
    }

    public function boot(Application \$app): void
    {
        // Boot module services, events, or listeners
    }
}
PHP;
        file_put_contents(
            $moduleDir . DIRECTORY_SEPARATOR . 'Providers' . DIRECTORY_SEPARATOR . "{$moduleName}ServiceProvider.php",
            $providerContent
        );

        // 5. Generate Controller
        $controllerContent = <<<PHP
<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/{$moduleName}/Controllers/{$moduleName}Controller.php
| Module    : Modules\\{$moduleName}
| Namespace : NetFusion\Modules\\{$moduleName}\Controllers
| Purpose   : Primary web controller for {$moduleName} Module.
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

namespace NetFusion\Modules\\{$moduleName}\Controllers;

use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;

class {$moduleName}Controller
{
    public function index(Request \$request): Response
    {
        return Response::json([
            'module'  => '{$moduleName}',
            'status'  => 'active',
            'message' => 'Welcome to {$moduleName} module!',
        ]);
    }
}
PHP;
        file_put_contents(
            $moduleDir . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . "{$moduleName}Controller.php",
            $controllerContent
        );

        // 6. Generate Routes/web.php
        $webRouteContent = <<<PHP
<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/{$moduleName}/Routes/web.php
| Module    : Modules\\{$moduleName}
| Namespace : None (Module Web Routes)
| Purpose   : Web routing definitions for {$moduleName} Module.
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

use NetFusion\Core\Routing\Router;
use NetFusion\Modules\\{$moduleName}\Controllers\\{$moduleName}Controller;

/** @var Router \$router */
\$router->get('/{$slug}', [{$moduleName}Controller::class, 'index'])->name('{$slug}.index');
PHP;
        file_put_contents(
            $moduleDir . DIRECTORY_SEPARATOR . 'Routes' . DIRECTORY_SEPARATOR . 'web.php',
            $webRouteContent
        );

        // 7. Generate Routes/api.php
        $apiRouteContent = <<<PHP
<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/{$moduleName}/Routes/api.php
| Module    : Modules\\{$moduleName}
| Namespace : None (Module API Routes)
| Purpose   : REST API routing definitions for {$moduleName} Module (mounted at /api/{$slug}).
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

use NetFusion\Core\Routing\Router;
use NetFusion\Core\Http\Response;

/** @var Router \$router */
\$router->get('/status', function () {
    return Response::json([
        'module' => '{$slug}',
        'api'    => 'active',
    ]);
})->name('api.{$slug}.status');
PHP;
        file_put_contents(
            $moduleDir . DIRECTORY_SEPARATOR . 'Routes' . DIRECTORY_SEPARATOR . 'api.php',
            $apiRouteContent
        );

        $this->success("HMVC Module [{$moduleName}] successfully scaffolded!");
        $this->line("  Location: \033[36m{$moduleDir}\033[0m");
        $this->line("  Web Route: \033[32mGET /{$slug}\033[0m");
        $this->line("  API Route: \033[32mGET /api/{$slug}/status\033[0m");

        return 0;
    }
}
