<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
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

declare(strict_types=1);

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;
use NetFusion\Core\SchemaRegistry\Enums\CompatibilityMode;
use NetFusion\Core\SchemaRegistry\Enums\SchemaType;
use NetFusion\Core\SchemaRegistry\Schema;
use NetFusion\Core\SchemaRegistry\SchemaManager;

/**
 * Class SchemaCheckCommand
 *
 * CLI: php netfusion schema:check <subject> <file_or_json> [--mode=BACKWARD|FORWARD|FULL] [--type=AVRO|PROTOBUF|JSON_SCHEMA]
 */
class SchemaCheckCommand extends Command
{
    protected string $name = 'schema:check';
    protected string $description = 'Test candidate schema compatibility against existing subject versions without registering';

    public function handle(array $args = []): int
    {
        $subject = null;
        $defInput = null;
        $modeStr = 'BACKWARD';
        $typeStr = 'AVRO';

        foreach ($args as $arg) {
            if (!is_string($arg)) {
                continue;
            }
            if (str_starts_with($arg, '--mode=')) {
                $modeStr = strtoupper(substr($arg, 7));
            } elseif (str_starts_with($arg, '--type=')) {
                $typeStr = strtoupper(substr($arg, 7));
            } elseif (!str_starts_with($arg, '--')) {
                if ($subject === null) {
                    $subject = $arg;
                } elseif ($defInput === null) {
                    $defInput = $arg;
                }
            }
        }

        $subject = $subject ?? (string) $this->argument(0, 'default-subject');
        $defInput = $defInput ?? (string) $this->argument(1, '{}');

        if (file_exists($defInput)) {
            $definition = file_get_contents($defInput);
        } else {
            $definition = $defInput;
        }

        $schemaType = match ($typeStr) {
            'PROTOBUF' => SchemaType::PROTOBUF,
            'JSON_SCHEMA', 'JSON' => SchemaType::JSON_SCHEMA,
            default => SchemaType::AVRO,
        };

        $mode = match ($modeStr) {
            'FORWARD' => CompatibilityMode::FORWARD,
            'FULL' => CompatibilityMode::FULL,
            'NONE' => CompatibilityMode::NONE,
            default => CompatibilityMode::BACKWARD,
        };

        try {
            $manager = SchemaManager::getInstance();
            $registry = $manager->registry();
            $checker = $manager->checker();

            $existing = [];
            foreach ($registry->getAllVersions($subject) as $v) {
                $s = $registry->getBySubjectAndVersion($subject, $v);
                if ($s !== null) {
                    $existing[] = $s;
                }
            }

            $candidate = new Schema(0, $subject, 0, $schemaType, $definition);
            $result = $checker->check($candidate, $existing, $mode);

            if ($result['compatible']) {
                $this->info("Schema is COMPATIBLE with subject '{$subject}' under mode {$mode->value}.");
                return 0;
            }

            $this->error("Schema is INCOMPATIBLE with subject '{$subject}' under mode {$mode->value}:");
            foreach ($result['violations'] as $v) {
                $this->line("  - {$v}");
            }
            return 1;
        } catch (\Throwable $e) {
            $this->error("Compatibility check failed: " . $e->getMessage());
            return 1;
        }
    }
}
