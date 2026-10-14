<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/FormFieldAddCommand.php
| Module    : Forms
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : CLI command to add or update a dynamic custom field definition.
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
 * FormFieldAddCommand
 *
 * Usage:
 *   php netfusion form:add-field User department text --label="Department" --required
 *   php netfusion form:add-field Lead budget number --label="Estimated Budget" --rules="min:0"
 *   php netfusion form:add-field Product category select --label="Category" --options='{"1":"Electronics","2":"Fashion"}'
 */
class FormFieldAddCommand extends Command
{
    protected string $name        = 'form:add-field';
    protected string $description = 'Add or update a custom field definition for an entity.';

    public function handle(): int
    {
        $args = $this->arguments();

        if (count($args) < 3) {
            $this->error('Usage: php netfusion form:add-field <EntityType> <name> <type> [--label=...] [--required] [--rules=...] [--options=...]');
            $this->info('Supported types: text, number, select, textarea, date, checkbox, file, json');
            return 1;
        }

        $entityType = $args[0];
        $name       = strtolower($args[1]);
        $type       = strtolower($args[2]);

        $label      = (string)($this->option('label') ?: ucfirst(str_replace(['_', '-'], ' ', $name)));
        $rules      = (string)($this->option('rules') ?: '');
        $isRequired = (bool)$this->option('required');
        $optionsRaw = $this->option('options');
        $options    = [];

        if ($optionsRaw && is_string($optionsRaw)) {
            $decoded = json_decode($optionsRaw, true);
            if (is_array($decoded)) {
                $options = $decoded;
            }
        }

        /** @var FormSchema $schema */
        $schema = app('form.schema');

        try {
            $id = $schema->defineField(
                entityType: $entityType,
                name: $name,
                label: $label,
                type: $type,
                rules: $rules,
                options: $options,
                isRequired: $isRequired
            );

            $this->info("✔ Custom field [{$name}] successfully defined for entity [{$entityType}] (ID: {$id}).");
        } catch (\Exception $e) {
            $this->error("Failed to add field: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}