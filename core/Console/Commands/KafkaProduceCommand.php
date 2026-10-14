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

/**
 * Class KafkaProduceCommand
 *
 * CLI: php netfusion kafka:produce <topic> <message> [--key=...] [--partition=N]
 */
class KafkaProduceCommand extends Command
{
    protected string $name = 'kafka:produce';
    protected string $description = 'Produce and publish a message record to a Kafka topic';

    public function handle(array $args = []): int
    {
        $topic = null;
        $message = null;
        $key = null;
        $partition = null;

        $positional = [];
        for ($i = 0; $i < count($args); $i++) {
            $arg = (string) $args[$i];
            if (str_starts_with($arg, '--key=')) {
                $key = substr($arg, 6);
            } elseif ($arg === '--key' && isset($args[$i + 1])) {
                $key = (string) $args[++$i];
            } elseif (str_starts_with($arg, '--partition=')) {
                $partition = (int) substr($arg, 12);
            } elseif ($arg === '--partition' && isset($args[$i + 1])) {
                $partition = (int) $args[++$i];
            } elseif (!str_starts_with($arg, '-')) {
                $positional[] = $arg;
            }
        }

        if (empty($positional) && !empty($this->arguments)) {
            $positional = $this->arguments;
        }

        $topic = $positional[0] ?? null;
        $message = $positional[1] ?? null;

        if ($topic === null || $message === null) {
            $this->error('Usage: php netfusion kafka:produce <topic> <message> [--key=...] [--partition=N]');
            return 1;
        }

        $kafka = function_exists('kafka') ? kafka() : $this->app->make(\NetFusion\Core\Kafka\KafkaManager::class);

        try {
            $producer = $kafka->producer();
            $record = $producer->send($topic, $message, $key, $partition);
            $this->info("Message published to {$record->topic}-{$record->partition} at offset {$record->offset}.");
            return 0;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return 1;
        }
    }
}
