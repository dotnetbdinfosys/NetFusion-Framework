<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Console/Commands/WitParseCommand.php
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

class WitParseCommand extends Command
{
    protected string $signature = 'wit:parse {file? : WIT specification file path} {--source= : Inlined WIT code}';
    protected string $description = 'Parse, validate, and inspect a WebAssembly Interface Type (WIT) specification';

    public function handle(): int
    {
        $file = $this->argument(0);
        $inlineSource = $this->option('source');

        $witContent = '';
        if ($file && file_exists((string)$file)) {
            $witContent = (string)file_get_contents((string)$file);
        } elseif ($inlineSource) {
            $witContent = (string)$inlineSource;
        } else {
            // Default sample demo WIT
            $witContent = <<<'WIT'
package my-org:calculator@0.1.0;

interface operations {
    add: func(a: u32, b: u32) -> u32;
    greet: func(name: string) -> string;
}

world service {
    export operations;
}
WIT;
        }

        $mgr = $this->app->has('wasm.component')
            ? $this->app->make('wasm.component')
            : ComponentManager::getInstance();

        $parsed = $mgr->parseWit($witContent);

        $this->info("=== WIT Specification Analysis ===");
        $this->line("Package    : " . ($parsed['package'] ?? 'anonymous'));
        $this->line("Interfaces : " . count($parsed['interfaces']));
        $this->line("Worlds     : " . count($parsed['worlds']));

        foreach ($parsed['interfaces'] as $name => $iface) {
            $this->line("Interface: {$name}");
            foreach ($iface->getFunctions() as $fnName => $fn) {
                $this->line("  - func {$fnName}() -> " . ($fn->getReturnTypeName() ?? 'void'));
            }
        }

        return 0;
    }
}
