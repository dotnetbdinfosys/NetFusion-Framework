<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/FormFieldListCommand.php
| Module    : Forms
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : CLI command to list all custom fields for a specific entity type.
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
use NetFusion\Core\Forms\FormSchema;

/**
 * FormFieldListCommand
 *
 * Usage:
 *   php netfusion form:fields User
 *   php netfusion form:fields Lead
 */
class FormFieldListCommand extends Command
{
    protected string $name        = 'form:fields';
    protected string $description = 'List all dynamic custom fields defined for an entity type.';

    public function handle(): int
    {
        $args = $this->arguments();

        if (empty($args)) {
            $this->error('Usage: php netfusion form:fields <EntityType>');
            $this->info('Example: php netfusion form:fields User');
            return 1;
        }

        $entityType = $args[0];

        /** @var FormSchema $schema */
        $schema = app('form.schema');

        $fields = $schema->getFieldsForEntity($entityType);

        if (empty($fields)) {
            $this->info("No custom fields defined for entity [{$entityType}].");
            $this->info("Use `php netfusion form:add-field {$entityType} <name> <type>` to add a field.");
            return 0;
        }

        $this->info('');
        $this->info("=== Custom Fields for Entity [{$entityType}] ===");
        $this->info('');

        $header = sprintf(
            ' %-4s | %-15s | %-20s | %-10s | %-8s | %-15s',
            'ID', 'Name', 'Label', 'Type', 'Required', 'Rules'
        );
        $this->info($header);
        $this->info(str_repeat('-', strlen($header)));

        foreach ($fields as $f) {
            $this->info(sprintf(
                ' %-4s | %-15s | %-20s | %-10s | %-8s | %-15s',
                $f['id'],
                $f['name'],
                $f['label'],
                $f['type'],
                $f['is_required'] ? 'Yes' : 'No',
                $f['rules'] ?: '-'
            ));
        }

        $this->info('');
        $this->info(sprintf("Total fields: %d", count($fields)));
        $this->info('');

        return 0;
    }
}