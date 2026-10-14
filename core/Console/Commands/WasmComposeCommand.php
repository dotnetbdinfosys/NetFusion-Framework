<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Console/Commands/WasmComposeCommand.php
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

class WasmComposeCommand extends Command
{
    protected string $signature = 'wasm:compose {--producer= : Producer component name} {--consumer= : Consumer component name} {--output= : Composed component name}';
    protected string $description = 'Compose two WebAssembly components into an integrated pipeline';

    public function handle(): int
    {
        $producer = (string)($this->option('producer') ?: 'filter_comp');
        $consumer = (string)($this->option('consumer') ?: 'sink_comp');
        $output   = (string)($this->option('output') ?: 'pipeline_app');

        $mgr = $this->app->has('wasm.component')
            ? $this->app->make('wasm.component')
            : ComponentManager::getInstance();

        $linker = $mgr->getLinker();
        $prodModule = $mgr->newModule($producer);
        $consModule = $mgr->newModule($consumer);

        $composed = $linker->compose($prodModule, $consModule, $output);

        $this->info("Successfully composed components '{$producer}' and '{$consumer}' into '{$composed->getName()}'.");
        return 0;
    }
}
