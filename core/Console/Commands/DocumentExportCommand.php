<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/DocumentExportCommand.php
| Module    : Documents
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : CLI command to export model records into CSV or Excel format.
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
use NetFusion\Core\Documents\DocumentManager;

/**
 * DocumentExportCommand
 *
 * Usage:
 *   php netfusion document:export User csv --output=storage/users.csv
 *   php netfusion document:export Lead excel --output=storage/leads.xls --limit=100
 */
class DocumentExportCommand extends Command
{
    protected string $name        = 'document:export';
    protected string $description = 'Export model database records into CSV or Excel XML file.';

    public function handle(): int
    {
        $args = $this->arguments();

        if (count($args) < 2) {
            $this->error('Usage: php netfusion document:export <ModelClass> <csv|excel> [--output=...] [--limit=1000]');
            $this->info('Example: php netfusion document:export User csv --output=storage/users.csv');
            return 1;
        }

        $modelName = $args[0];
        $format    = strtolower($args[1]);
        $outputPath = (string)($this->option('output') ?: "storage/{$modelName}_export.{$format}");
        $limit      = (int)($this->option('limit') ?: 1000);

        // Resolve model class
        $modelClass = $modelName;
        if (!class_exists($modelClass)) {
            $candidates = [
                "NetFusion\\App\\Models\\{$modelName}",
                "App\\Models\\{$modelName}",
            ];
            foreach ($candidates as $c) {
                if (class_exists($c)) {
                    $modelClass = $c;
                    break;
                }
            }
        }

        if (!class_exists($modelClass)) {
            $this->error("Model class [{$modelName}] not found.");
            return 1;
        }

        /** @var DocumentManager $manager */
        $manager = app('document');

        try {
            // Query model records
            $records = $modelClass::query()->limit($limit)->get();

            if (empty($records)) {
                $this->info("No records found in model [{$modelClass}].");
                return 0;
            }

            // Extract headers from first record
            $first = (array)$records[0];
            $headers = array_keys($first);

            if ($format === 'csv') {
                $exporter = $manager->csv($records, $headers);
            } elseif ($format === 'excel' || $format === 'xls') {
                $exporter = $manager->excel($records, $headers, $modelName);
            } else {
                $this->error("Unsupported format [{$format}]. Supported formats: csv, excel");
                return 1;
            }

            $success = $exporter->store($outputPath);

            if ($success) {
                $this->info("✔ Exported " . count($records) . " records to [{$outputPath}] successfully.");
                return 0;
            } else {
                $this->error("Failed to write export file to [{$outputPath}].");
                return 1;
            }
        } catch (\Exception $e) {
            $this->error("Export failed: " . $e->getMessage());
            return 1;
        }
    }
}