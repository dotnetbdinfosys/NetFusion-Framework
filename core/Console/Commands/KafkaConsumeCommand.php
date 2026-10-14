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
use NetFusion\Core\Kafka\Enums\AutoOffsetReset;

/**
 * Class KafkaConsumeCommand
 *
 * CLI: php netfusion kafka:consume <topic> [--group=...] [--offset=earliest|latest] [--max=10]
 */
class KafkaConsumeCommand extends Command
{
    protected string $name = 'kafka:consume';
    protected string $description = 'Consume messages from a Kafka topic or consumer group';

    public function handle(array $args = []): int
    {
        $topic = null;
        $groupId = null;
        $offsetReset = AutoOffsetReset::EARLIEST;
        $maxRecords = 10;

        $positional = [];
        for ($i = 0; $i < count($args); $i++) {
            $arg = (string) $args[$i];
            if (str_starts_with($arg, '--group=')) {
                $groupId = substr($arg, 8);
            } elseif ($arg === '--group' && isset($args[$i + 1])) {
                $groupId = (string) $args[++$i];
            } elseif (str_starts_with($arg, '--offset=')) {
                $val = substr($arg, 9);
                $offsetReset = AutoOffsetReset::tryFrom(strtolower($val)) ?? AutoOffsetReset::EARLIEST;
            } elseif (str_starts_with($arg, '--max=')) {
                $maxRecords = (int) substr($arg, 6);
            } elseif (!str_starts_with($arg, '-')) {
                $positional[] = $arg;
            }
        }

        if (empty($positional) && !empty($this->arguments)) {
            $positional = $this->arguments;
        }

        $topic = $positional[0] ?? null;

        if ($topic === null) {
            $this->error('Usage: php netfusion kafka:consume <topic> [--group=...] [--offset=earliest|latest] [--max=N]');
            return 1;
        }

        $kafka = function_exists('kafka') ? kafka() : $this->app->make(\NetFusion\Core\Kafka\KafkaManager::class);

        $consumer = $kafka->consumer($groupId, $offsetReset);
        $consumer->subscribe([$topic]);

        $this->info("Polling records from topic '{$topic}' (Group: " . ($groupId ?? 'standalone') . ")...");
        $records = $consumer->poll(timeoutMs: 100, maxRecords: $maxRecords);

        if (empty($records)) {
            $this->line('No records available.');
            $consumer->close();
            return 0;
        }

        foreach ($records as $record) {
            $keyStr = $record->key !== null ? " [Key: {$record->key}]" : "";
            $this->line("[{$record->topic}-{$record->partition} @ {$record->offset}]{$keyStr} => {$record->value}");
        }

        $consumer->close();
        return 0;
    }
}
