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
use NetFusion\Core\SchemaRegistry\CloudEvents\CloudEvent;
use NetFusion\Core\SchemaRegistry\SchemaManager;

/**
 * Class CloudEventPublishCommand
 *
 * CLI: php netfusion cloudevent:publish <type> <source> [--data=<json>] [--subject=<subject>]
 */
class CloudEventPublishCommand extends Command
{
    protected string $name = 'cloudevent:publish';
    protected string $description = 'Publish a CloudEvent v1.0 message across the distributed event mesh';

    public function handle(array $args = []): int
    {
        $type = null;
        $source = null;
        $dataInput = '{}';
        $subject = null;

        foreach ($args as $arg) {
            if (!is_string($arg)) {
                continue;
            }
            if (str_starts_with($arg, '--data=')) {
                $dataInput = substr($arg, 7);
            } elseif (str_starts_with($arg, '--subject=')) {
                $subject = substr($arg, 10);
            } elseif (!str_starts_with($arg, '--')) {
                if ($type === null) {
                    $type = $arg;
                } elseif ($source === null) {
                    $source = $arg;
                }
            }
        }

        $type = $type ?? (string) $this->argument(0, 'user.created');
        $source = $source ?? (string) $this->argument(1, '/services/auth');
        $data = json_decode($dataInput, true) ?? $dataInput;

        try {
            $event = CloudEvent::create(
                type: $type,
                source: $source,
                data: $data,
                subject: $subject
            );

            $manager = SchemaManager::getInstance();
            $results = $manager->publishEvent($event);
            $dispatchedCount = count($results);

            $this->info("CloudEvent dispatched successfully:");
            $this->line("  ID:           {$event->getId()}");
            $this->line("  Type:         {$event->getType()}");
            $this->line("  Source:       {$event->getSource()}");
            $this->line("  Time:         {$event->getTime()}");
            $this->line("  Subscribers:  {$dispatchedCount} notified");

            return 0;
        } catch (\Throwable $e) {
            $this->error("Failed to publish CloudEvent: " . $e->getMessage());
            return 1;
        }
    }
}
