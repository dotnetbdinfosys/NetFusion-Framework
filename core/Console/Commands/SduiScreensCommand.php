<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;
use NetFusion\Core\SDUI\SDUIManager;

/**
 * CLI command to list registered SDUI screens and templates.
 * Usage: php netfusion sdui:screens [--info=home]
 */
class SduiScreensCommand extends Command
{
    protected string $name = 'sdui:screens';
    protected string $description = 'List registered Server-Driven UI (SDUI) screens and inspect layout templates';

    public function handle(): int
    {
        /** @var SDUIManager $manager */
        $manager = function_exists('app') && app()->has('sdui') ? app('sdui') : new SDUIManager();

        $infoScreen = $this->option('info');

        if ($infoScreen !== null) {
            if (!$manager->getEngine()->hasScreen($infoScreen)) {
                $this->error("Screen '{$infoScreen}' is not registered.");
                return 1;
            }

            $rendered = $manager->render($infoScreen);
            $this->info("=== SDUI Screen Details: {$infoScreen} ===");
            $this->line(json_encode($rendered, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return 0;
        }

        $this->info("Server-Driven UI (SDUI) Engine initialized.");
        $this->line("Use --info=<screen_id> to inspect a specific screen layout schema.");

        return 0;
    }
}
