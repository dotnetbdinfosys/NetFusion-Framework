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

/**
 * CLI command to generate and inspect Webpack 5 compliant mf-manifest.json schema.
 * Usage: php netfusion mfe:manifest [--host=netfusion_host]
 */
class MfeManifestCommand extends Command
{
    protected string $name = 'mfe:manifest';
    protected string $description = 'Generate and display Webpack 5 Module Federation manifest schema';

    public function handle(array $args = []): int
    {
        $host = 'host';
        foreach ($args as $arg) {
            if (str_starts_with((string)$arg, '--host=')) {
                $host = substr((string)$arg, 7);
            }
        }

        $mfe = function_exists('mfe') ? mfe() : $this->app->make(\NetFusion\Core\MFE\MfeManager::class);
        $manifest = $mfe->manifest($host);

        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $this->line($json);

        return 0;
    }
}
