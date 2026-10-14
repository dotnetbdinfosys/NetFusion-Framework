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
use NetFusion\Core\SDUI\Exceptions\ComponentValidationException;
use NetFusion\Core\SDUI\SDUIManager;

/**
 * CLI command to validate SDUI JSON schema files or inline schema.
 * Usage: php netfusion sdui:validate [--file=layout.json] [--json='{"id":"c1","type":"text"}']
 */
class SduiValidateCommand extends Command
{
    protected string $name = 'sdui:validate';
    protected string $description = 'Validate Server-Driven UI JSON component or screen schema against specification';

    public function handle(): int
    {
        /** @var SDUIManager $manager */
        $manager = function_exists('app') && app()->has('sdui') ? app('sdui') : new SDUIManager();

        $file = $this->option('file');
        $json = $this->option('json');

        $schemaContent = null;
        if ($file !== null && file_exists($file)) {
            $schemaContent = file_get_contents($file);
        } elseif ($json !== null) {
            $schemaContent = $json;
        }

        if ($schemaContent === null) {
            $this->error("Provide either --file=<path> or --json=<string> to validate.");
            return 1;
        }

        $decoded = json_decode($schemaContent, true);
        if (!is_array($decoded)) {
            $this->error("Invalid JSON syntax provided.");
            return 1;
        }

        try {
            $manager->validateSchema($decoded);
            $this->info("SDUI Schema is valid!");
            return 0;
        } catch (ComponentValidationException $e) {
            $this->error("Validation failed: " . $e->getMessage());
            foreach ($e->getErrors() as $field => $msg) {
                $this->line(" - [{$field}]: {$msg}");
            }
            return 1;
        }
    }
}
