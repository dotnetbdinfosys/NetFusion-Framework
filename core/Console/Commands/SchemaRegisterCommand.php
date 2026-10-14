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
use NetFusion\Core\SchemaRegistry\Enums\SchemaType;
use NetFusion\Core\SchemaRegistry\SchemaManager;

/**
 * Class SchemaRegisterCommand
 *
 * CLI: php netfusion schema:register <subject> <file_or_json> [--type=AVRO|PROTOBUF|JSON_SCHEMA]
 */
class SchemaRegisterCommand extends Command
{
    protected string $name = 'schema:register';
    protected string $description = 'Register a new schema version under a subject with compatibility checking';

    public function handle(array $args = []): int
    {
        $subject = null;
        $defInput = null;
        $typeStr = 'AVRO';

        foreach ($args as $k => $arg) {
            if (!is_string($arg)) {
                continue;
            }
            if (str_starts_with($arg, '--type=')) {
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

        // Check if $defInput is a file path
        if (file_exists($defInput)) {
            $definition = file_get_contents($defInput);
        } else {
            $definition = $defInput;
        }

        $schemaType = match (strtoupper($typeStr)) {
            'PROTOBUF' => SchemaType::PROTOBUF,
            'JSON_SCHEMA', 'JSON' => SchemaType::JSON_SCHEMA,
            default => SchemaType::AVRO,
        };

        try {
            $manager = SchemaManager::getInstance();
            $schema = $manager->register($subject, $definition, $schemaType);

            $this->info("Successfully registered schema for subject '{$subject}':");
            $this->line("  ID:          {$schema->getId()}");
            $this->line("  Version:     {$schema->getVersion()}");
            $this->line("  Type:        {$schema->getType()->value}");
            $this->line("  Fingerprint: {$schema->getFingerprint()}");

            return 0;
        } catch (\Throwable $e) {
            $this->error("Schema registration failed: " . $e->getMessage());
            return 1;
        }
    }
}
