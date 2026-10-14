<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Console/Commands/WasmComponentRunCommand.php
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
use NetFusion\Core\Wasm\Component\ComponentManager;
use NetFusion\Core\Wasm\Component\Model\ComponentExport;

class WasmComponentRunCommand extends Command
{
    protected string $signature = 'wasm:component-run {component? : Component name} {--func= : Function name} {--args=* : Arguments}';
    protected string $description = 'Instantiate and execute an exported WebAssembly component function';

    public function handle(): int
    {
        $compName = (string)($this->argument(0) ?: 'greeter_component');
        $funcName = (string)($this->option('func') ?: 'greet');
        $args     = (array)($this->option('args') ?: ['World']);

        $mgr = $this->app->has('wasm.component')
            ? $this->app->make('wasm.component')
            : ComponentManager::getInstance();

        $module = $mgr->newModule($compName);
        $module->addExport(new ComponentExport(
            $funcName,
            fn($name = 'World') => "Hello, {$name} from Wasm Component Model!"
        ));

        $instance = $mgr->instantiate($compName);
        $result = $instance->call($funcName, $args);

        $this->info("Execution completed successfully:");
        $this->line("Result: " . (is_scalar($result) ? (string)$result : json_encode($result)));

        return 0;
    }
}
