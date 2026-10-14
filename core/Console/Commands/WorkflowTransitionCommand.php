<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/WorkflowTransitionCommand.php
| Module    : Workflow
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : CLI command to apply a state machine transition on a model record.
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
use NetFusion\Core\Workflow\WorkflowManager;

class WorkflowTransitionCommand extends Command
{
    protected string $name        = 'workflow:apply';
    protected string $description = 'Apply a state transition to a specific model instance.';

    public function handle(): int
    {
        $args = $this->arguments();

        if (count($args) < 3) {
            $this->error('Usage: php netfusion workflow:apply <ModelClass> <id> <transition> [--actor=...] [--comment=...]');
            $this->info('Example: php netfusion workflow:apply Invoice 42 pay --actor=1 --comment="Paid via Stripe"');
            return 1;
        }

        $modelName  = $args[0];
        $id         = (int)$args[1];
        $transition = $args[2];
        $actorId    = $this->option('actor') ? (int)$this->option('actor') : null;
        $comment    = (string)($this->option('comment') ?: '');

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

        $record = $modelClass::find($id);
        if ($record === null) {
            $this->error("Record [{$modelName}] with ID [{$id}] not found.");
            return 1;
        }

        try {
            if (!method_exists($record, 'applyTransition')) {
                $this->error("Model [{$modelClass}] does not use HasWorkflow trait.");
                return 1;
            }

            $context = ['actor_id' => $actorId, 'comment' => $comment];
            $record->applyTransition($transition, $context);

            $this->info("✔ Transition [{$transition}] applied successfully to [{$modelName} #{$id}].");
            $this->info("New State: " . (method_exists($record, 'getAttribute') ? $record->getAttribute('status') : $record->status));
            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to apply transition: " . $e->getMessage());
            return 1;
        }
    }
}